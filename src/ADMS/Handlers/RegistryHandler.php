<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Handlers;

use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushResponse;
use ZkTeco\ADMS\Http\PushRouter;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\Negotiator;

/**
 * Handles `/iclock/registry`, the dedicated registration endpoint a PUSH-SDK
 * device polls to announce itself and receive a registry code, before (or
 * alongside) its `cdata` handshake.
 *
 * Registration is the same trust-but-gate act as the `cdata` handshake: negotiate
 * the device's generation/capabilities, record it (idempotently), then answer
 * with the generation's registry block. The serial number is guaranteed present
 * and allowlisted by the {@see PushRouter} before this handler runs.
 */
final class RegistryHandler
{
    public function __construct(
        private DeviceRegistry $registry,
        private Negotiator $negotiator,
        private GenerationSelector $generations,
    ) {}

    public function handle(PushRequest $request): PushResponse
    {
        $registered = $this->registry->register($this->negotiator->negotiate($request));

        return PushResponse::ok($this->generations->for($registered)->registryBlock($registered));
    }
}
