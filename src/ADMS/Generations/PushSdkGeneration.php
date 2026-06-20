<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Generations;

use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\ADMS\BiometricSink;
use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\ADMS\Commands\Intents\PushTemplate;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Parsing\BiodataParser;
use ZkTeco\ADMS\Parsing\RtlogParser;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\Values\BiometricTemplate;

/**
 * The PUSH-SDK generation: a superset of {@see LegacyGeneration}, adding the
 * `RTLOG` (real-time attendance) and `BIODATA` (biometric template) tables and a
 * dedicated `/iclock/registry` response.
 *
 * It is built by *composition* rather than inheritance — PUSH SDK reuses legacy
 * decoding for the shared tables and adds two of its own, so it holds a
 * {@see LegacyGeneration} and delegates to it for everything it does not own (see
 * docs/adr/0012). `RTLOG` attendance flows to the same {@see AttendanceSink} as
 * legacy `ATTLOG`, keeping the read path unified (see docs/adr/0009); the
 * attendance sink injected here is for `RTLOG`, while `ATTLOG` is handled through
 * the legacy delegate.
 *
 * One instance serves every PUSH-SDK protocol generation (PushV2 and PushV3);
 * which generations map to it is the selector's decision, not this class's.
 */
final class PushSdkGeneration implements Generation
{
    public function __construct(
        private LegacyGeneration $legacy,
        private RtlogParser $rtlogParser,
        private BiodataParser $biodataParser,
        private AttendanceSink $attendanceSink,
        private BiometricSink $biometricSink,
    ) {}

    public function ingest(string $table, PushRequest $request, string $serialNumber): IngestOutcome
    {
        return match ($table) {
            'RTLOG' => $this->ingestRtlog($request, $serialNumber),
            'BIODATA' => $this->ingestBiodata($request, $serialNumber),
            default => $this->legacy->ingest($table, $request, $serialNumber),
        };
    }

    private function ingestRtlog(PushRequest $request, string $serialNumber): IngestOutcome
    {
        $records = $this->rtlogParser->parse($request->body);

        foreach ($records as $record) {
            $this->attendanceSink->receive($record, $serialNumber);
        }

        return IngestOutcome::of(count($records));
    }

    private function ingestBiodata(PushRequest $request, string $serialNumber): IngestOutcome
    {
        $templates = $this->biodataParser->parse($request->body);

        foreach ($templates as $template) {
            $this->biometricSink->receive($template, $serialNumber);
        }

        return IngestOutcome::of(count($templates));
    }

    /**
     * The legacy config block plus the PUSH-SDK tail that opts the device into the
     * biometric and real-time channels and tells it where to resume them. The tail
     * keys are firmware-sensitive and provisional pending a capture (see
     * docs/adr/0005).
     */
    public function configBlock(RegisteredDevice $device): string
    {
        $biodata = $device->stampFor('BIODATA')?->value ?? '0';
        $rtlog = $device->stampFor('RTLOG')?->value ?? '0';

        return $this->legacy->configBlock($device).implode("\n", [
            'BioDataFun=1',
            'RtDataFun=1',
            'BioStamp='.$biodata,
            'RtStamp='.$rtlog,
        ])."\n";
    }

    /**
     * The `/iclock/registry` acknowledgement. The exact body is firmware-sensitive
     * and provisional until pinned against a real capture (see docs/adr/0005); the
     * device's serial is echoed as a registry code, the widely-seen shape.
     */
    public function registryBlock(RegisteredDevice $device): string
    {
        return 'RegistryCode='.$device->serialNumber."\n";
    }

    /**
     * PUSH SDK shares legacy's command vocabulary except for the one verb whose
     * wire form genuinely diverges: a template is pushed to the `BIODATA` table
     * here, not legacy's `FINGERTMP`. Every other intent — including the
     * `USERINFO` upsert, which is wire-identical across generations — is rendered
     * by the composed legacy generation (see docs/adr/0013). The `BIODATA`
     * command layout is provisional until a real capture (see docs/adr/0005).
     */
    public function renderCommand(DeviceCommand $command): string
    {
        return match (true) {
            $command instanceof PushTemplate => $this->renderBioData($command->template),
            default => $this->legacy->renderCommand($command),
        };
    }

    private function renderBioData(BiometricTemplate $template): string
    {
        $fields = [
            'Pin='.$template->userId,
            'No='.$template->index,
            'Type='.$template->type,
            'Valid='.($template->valid ? '1' : '0'),
            'Size='.strlen($template->data),
            'Tmp='.$template->data,
        ];

        return 'DATA UPDATE BIODATA '.implode("\t", $fields);
    }
}
