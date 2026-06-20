<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Http;

use ZkTeco\ADMS\Handlers\CdataHandler;
use ZkTeco\ADMS\Handlers\DevicecmdHandler;
use ZkTeco\ADMS\Handlers\GetrequestHandler;
use ZkTeco\ADMS\Handlers\RegistryHandler;
use ZkTeco\ADMS\Registry\DeviceRegistry;

/**
 * The single entry point for ADMS traffic: gate the request, then dispatch it to
 * the handler for its endpoint and method.
 *
 * Gating happens here, once, for every endpoint (see docs/adr/0010): a request
 * with no serial number is a bad request, and a serial the registry does not
 * admit (blocked, or un-allowlisted in strict mode) is rejected. Handlers
 * downstream may therefore assume the serial is present and admitted — though
 * admitted is not the same as approved, so they still gate ingestion on the
 * device's status.
 */
final class PushRouter
{
    public function __construct(
        private DeviceRegistry $registry,
        private CdataHandler $cdata,
        private GetrequestHandler $getrequest,
        private DevicecmdHandler $devicecmd,
        private RegistryHandler $registryEndpoint,
    ) {}

    public function dispatch(PushRequest $request): PushResponse
    {
        $serial = $request->serialNumber();

        if ($serial === null || $serial === '') {
            return PushResponse::badRequest('Missing serial number.');
        }

        if (! $this->registry->admits($serial)) {
            return PushResponse::unauthorized();
        }

        return match ($request->method.' '.$request->endpoint()) {
            'GET cdata' => $this->cdata->handshake($request),
            'POST cdata' => $this->cdata->receiveData($request),
            'GET getrequest' => $this->getrequest->handle($request),
            'POST devicecmd' => $this->devicecmd->handle($request),
            'GET registry' => $this->registryEndpoint->handle($request),
            default => PushResponse::notFound(),
        };
    }
}
