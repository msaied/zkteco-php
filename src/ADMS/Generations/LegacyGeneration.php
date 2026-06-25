<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Generations;

use DateTimeImmutable;
use ZkTeco\ADMS\AttendancePhotoSink;
use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\ADMS\Commands\Intents\ClearData;
use ZkTeco\ADMS\Commands\Intents\ClearLog;
use ZkTeco\ADMS\Commands\Intents\ClearPhoto;
use ZkTeco\ADMS\Commands\Intents\DeleteUser;
use ZkTeco\ADMS\Commands\Intents\Disable;
use ZkTeco\ADMS\Commands\Intents\Enable;
use ZkTeco\ADMS\Commands\Intents\PowerOff;
use ZkTeco\ADMS\Commands\Intents\PushTemplate;
use ZkTeco\ADMS\Commands\Intents\QueryData;
use ZkTeco\ADMS\Commands\Intents\Reboot;
use ZkTeco\ADMS\Commands\Intents\Restart;
use ZkTeco\ADMS\Commands\Intents\SyncTime;
use ZkTeco\ADMS\Commands\Intents\UpsertUser;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\OperationLogSink;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Parsing\AttphotoParser;
use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\ADMS\UserSink;
use ZkTeco\Exceptions\CommandException;
use ZkTeco\TCP\Protocol\NameField;
use ZkTeco\Values\BiometricTemplate;
use ZkTeco\Values\User;

/**
 * The legacy ADMS generation: the `cdata` text protocol with the `ATTLOG`,
 * `OPERLOG`/`USERINFO`, and `ATTPHOTO` tables (M1–M3). Any other table — a
 * PUSH-SDK `BIODATA`/`RTLOG` row reaching a legacy device — is left ignored.
 *
 * This holds the per-type read-path sinks and decodes each owned table to them,
 * exactly as the handler did before the generation strategy split the work out
 * (see docs/adr/0012). PUSH SDK reuses this by composition.
 */
final class LegacyGeneration implements Generation
{
    public function __construct(
        private AttlogParser $attlogParser,
        private OperlogParser $operlogParser,
        private AttphotoParser $attphotoParser,
        private AttendanceSink $attendanceSink,
        private OperationLogSink $operationLogSink,
        private UserSink $userSink,
        private AttendancePhotoSink $attendancePhotoSink,
        private string $nameEncoding = NameField::DEFAULT_ENCODING,
    ) {}

    public function ingest(string $table, PushRequest $request, string $serialNumber): IngestOutcome
    {
        return match ($table) {
            'ATTLOG' => $this->ingestAttlog($request, $serialNumber),
            'OPERLOG', 'USERINFO' => $this->ingestOperlog($request, $serialNumber),
            'ATTPHOTO' => $this->ingestAttphoto($request, $serialNumber),
            default => IngestOutcome::ignored(),
        };
    }

    private function ingestAttlog(PushRequest $request, string $serialNumber): IngestOutcome
    {
        $records = $this->attlogParser->parse($request->body);

        foreach ($records as $record) {
            $this->attendanceSink->receive($record, $serialNumber);
        }

        return IngestOutcome::of(count($records));
    }

    /**
     * An `OPERLOG` (or standalone `USERINFO`) upload multiplexes two record
     * kinds: operation log entries and the device's User sync. Each flows to its
     * own sink (see docs/adr/0011).
     */
    private function ingestOperlog(PushRequest $request, string $serialNumber): IngestOutcome
    {
        $batch = $this->operlogParser->parse($request->body);

        foreach ($batch->operations as $entry) {
            $this->operationLogSink->receive($entry, $serialNumber);
        }

        foreach ($batch->users as $user) {
            $this->userSink->receive($user, $serialNumber);
        }

        return IngestOutcome::of($batch->count());
    }

    private function ingestAttphoto(PushRequest $request, string $serialNumber): IngestOutcome
    {
        $photo = $this->attphotoParser->parse($request);

        if ($photo === null) {
            return IngestOutcome::of(0);
        }

        $this->attendancePhotoSink->receive($photo, $serialNumber);

        return IngestOutcome::of(1);
    }

    /**
     * The config block a device reads after handshake: where to resume each
     * table (its stamps) and how to push. The exact keys are firmware-sensitive
     * and provisional pending a capture (see docs/adr/0005); these are the
     * widely-supported defaults, with `Realtime=1` so attendance is pushed as it
     * happens.
     */
    public function configBlock(RegisteredDevice $device): string
    {
        $attlog = $device->stampFor('ATTLOG')?->value ?? '0';
        $operlog = $device->stampFor('OPERLOG')?->value ?? '0';

        return implode("\n", [
            'GET OPTION FROM: '.$device->serialNumber,
            'Stamp='.$attlog,
            'OpStamp='.$operlog,
            'ErrorDelay=30',
            'Delay=10',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=1111111111',
            'Realtime=1',
            'Encrypt=0',
        ])."\n";
    }

    /**
     * Legacy devices register through the `cdata` handshake and never poll a
     * dedicated registry endpoint; a benign acknowledgement is returned in case a
     * request is misrouted here.
     */
    public function registryBlock(RegisteredDevice $device): string
    {
        return "RegistryCode=0\n";
    }

    /**
     * Render a command intent into a legacy `cdata` instruction. The control and
     * data verbs below are the widely-supported ADMS forms; the `SET OPTIONS`
     * time encoding and the `FINGERTMP` template layout are firmware-sensitive
     * and provisional until pinned against a real capture (see docs/adr/0005).
     * `PowerOff`/`Enable`/`Disable` have no canonical ADMS verb and are rendered
     * best-effort, to be confirmed or dropped once hardware is available.
     */
    public function renderCommand(DeviceCommand $command): string
    {
        return match (true) {
            $command instanceof Reboot, $command instanceof Restart => 'REBOOT',
            $command instanceof PowerOff => 'POWEROFF',
            $command instanceof Enable => 'ENABLE',
            $command instanceof Disable => 'DISABLE',
            $command instanceof ClearData => 'CLEAR DATA',
            $command instanceof ClearLog => 'CLEAR LOG',
            $command instanceof ClearPhoto => 'CLEAR PHOTO',
            $command instanceof SyncTime => 'SET OPTIONS DateTime='.$this->encodeDateTime($command->at),
            $command instanceof QueryData => 'DATA QUERY '.$command->table,
            $command instanceof DeleteUser => 'DATA DELETE USERINFO PIN='.$command->pin,
            $command instanceof UpsertUser => $this->renderUserInfo($command->user),
            $command instanceof PushTemplate => $this->renderFingerTemplate($command->template),
            default => throw CommandException::unsupportedCommand($command::class, 'Legacy'),
        };
    }

    /**
     * The `USERINFO` upsert form, shared by both generations. Keyed by the PIN;
     * the device-local `uid` slot is irrelevant to a push and omitted.
     *
     * The name is re-encoded to the device's codepage ({@see $nameEncoding}, e.g.
     * Windows-1256 for Arabic firmware) for the same reason the socket path does
     * it in {@see NameField}: the panel reads these bytes through its own codepage,
     * so a raw UTF-8 name renders as mojibake. A UTF-8 device leaves it untouched.
     */
    private function renderUserInfo(User $user): string
    {
        $fields = [
            'PIN='.$user->userId,
            'Name='.NameField::toCodepage($user->name, $this->nameEncoding),
            'Pri='.$user->privilege->value,
            'Passwd='.($user->password ?? ''),
            'Card='.($user->cardNumber ?? ''),
            'Grp='.$user->groupId,
        ];

        return 'DATA UPDATE USERINFO '.implode("\t", $fields);
    }

    /**
     * The legacy `FINGERTMP` template form. PUSH SDK overrides this with its
     * `BIODATA` counterpart; both layouts are provisional (see docs/adr/0005).
     */
    private function renderFingerTemplate(BiometricTemplate $template): string
    {
        $fields = [
            'PIN='.$template->userId,
            'FID='.$template->index,
            'Valid='.($template->valid ? '1' : '0'),
            'Size='.strlen($template->data),
            'TMP='.$template->data,
        ];

        return 'DATA UPDATE FINGERTMP '.implode("\t", $fields);
    }

    /**
     * The device's packed wall-clock integer for `SET OPTIONS DateTime=`. This is
     * the zkemsdk `EncodeTime` formula, kept here rather than imported from the
     * ZK socket stack so the two adapters stay decoupled (see docs/adr/0007); the
     * value is provisional until confirmed against a real device.
     */
    private function encodeDateTime(DateTimeImmutable $time): int
    {
        $year = (int) $time->format('Y');
        $month = (int) $time->format('n');
        $day = (int) $time->format('j');
        $hour = (int) $time->format('G');
        $minute = (int) $time->format('i');
        $second = (int) $time->format('s');

        return (($year % 100) * 12 * 31 + ($month - 1) * 31 + $day - 1)
            * (24 * 60 * 60) + ($hour * 60 + $minute) * 60 + $second;
    }
}
