<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Generations;

use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;

/**
 * Resolves a Registered device to the {@see Generation} strategy that serves its
 * {@see ProtocolGeneration}.
 *
 * The map is keyed by the generation's backing value and supplied by the bridge,
 * so a single PUSH-SDK strategy can serve several generation values (PushV2 and
 * PushV3) without this class knowing the family. Any generation with no mapped
 * strategy falls back to the legacy one — the same "unrecognised means legacy"
 * stance the {@see ProtocolGeneration::fromPushVersion()}
 * negotiation already takes.
 */
final class GenerationSelector
{
    /**
     * @param  array<string, Generation>  $byGeneration  keyed by ProtocolGeneration->value
     */
    public function __construct(
        private array $byGeneration,
        private Generation $fallback,
    ) {}

    public function for(RegisteredDevice $device): Generation
    {
        return $this->byGeneration[$device->generation->value] ?? $this->fallback;
    }
}
