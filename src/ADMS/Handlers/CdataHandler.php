<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Handlers;

use ZkTeco\ADMS\Generations\Generation;
use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushResponse;
use ZkTeco\ADMS\Http\PushRouter;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\ADMS\Registry\Negotiator;
use ZkTeco\ADMS\Registry\Stamp;

/**
 * Handles `/iclock/cdata`, the device's main channel: the GET handshake where a
 * device registers and asks for its config, and the POST where it uploads table
 * data.
 *
 * The handler owns only the protocol envelope — note the device alive, gate on
 * approval, advance the per-table stamp, and shape the reply. *What* each table
 * decodes into, where it routes, and what the config block says belongs to the
 * device's {@see Generation}, selected from its protocol
 * generation (see docs/adr/0012). The serial number is guaranteed present and
 * allowlisted by the {@see PushRouter} before this handler runs.
 */
final class CdataHandler
{
    public function __construct(
        private DeviceRegistry $registry,
        private Negotiator $negotiator,
        private GenerationSelector $generations,
    ) {}

    /**
     * GET handshake: negotiate the device's generation/capabilities, register
     * it, and answer with the generation's config block that tells it how and
     * from where to upload.
     */
    public function handshake(PushRequest $request): PushResponse
    {
        $registered = $this->registry->register($this->negotiator->negotiate($request));

        return PushResponse::ok($this->generations->for($registered)->configBlock($registered));
    }

    /**
     * POST upload. The device sends one table per request; the device's
     * generation decodes the ones it understands into their read paths, and the
     * handler advances the per-table stamp. A table the generation does not own
     * is accepted as a no-op — its stamp is not advanced — so the device keeps
     * moving rather than retrying.
     *
     * Ingestion is gated on approval: an unapproved (pending) device's upload is
     * held — not parsed, not emitted, its stamp not advanced — and answered with
     * a retry so the device keeps the batch until an operator approves it (see
     * {@see DeviceStatus} and docs/adr/0010).
     */
    public function receiveData(PushRequest $request): PushResponse
    {
        $serial = (string) $request->serialNumber();
        $table = strtoupper($request->param('table') ?? '');

        $this->registry->markSeen($serial);

        // A device may upload before handshaking; ensure it is on record so its
        // status can be judged.
        $device = $this->registry->find($serial)
            ?? $this->registry->register($this->negotiator->negotiate($request));

        if (! $device->isApproved()) {
            return PushResponse::unavailable('Device pending approval.');
        }

        $outcome = $this->generations->for($device)->ingest($table, $request, $serial);

        if (! $outcome->handled) {
            return PushResponse::ok();
        }

        $this->advanceStamp($serial, $table, $request->param('Stamp'));

        return PushResponse::ok('OK: '.$outcome->count);
    }

    private function advanceStamp(string $serial, string $table, ?string $stamp): void
    {
        if ($stamp !== null && $stamp !== '') {
            $this->registry->updateStamp($serial, new Stamp($table, $stamp));
        }
    }
}
