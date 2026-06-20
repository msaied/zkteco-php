<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

use ZkTeco\ADMS\Http\PushRequest;

/**
 * Reads a handshake and decides what kind of device just dialed in: its
 * protocol generation and advertised capabilities.
 *
 * It builds an unsaved {@see RegisteredDevice} from the request; persisting it
 * (and stamping the last-seen time) is the {@see DeviceRegistry}'s job. The
 * last-seen time is therefore left null here.
 */
final class Negotiator
{
    public function negotiate(PushRequest $request): RegisteredDevice
    {
        return new RegisteredDevice(
            serialNumber: $request->serialNumber() ?? '',
            generation: ProtocolGeneration::fromPushVersion($request->param('pushver')),
            capabilities: $this->capabilitiesFrom($request),
        );
    }

    private function capabilitiesFrom(PushRequest $request): Capabilities
    {
        return new Capabilities(
            fingerprint: $request->param('FingerFunOn') === '1',
            face: $request->param('FaceFunOn') === '1',
            userPhoto: $request->param('UserPicURLFunOn') === '1' || $request->param('PhotoFunOn') === '1',
            raw: $request->query,
        );
    }
}
