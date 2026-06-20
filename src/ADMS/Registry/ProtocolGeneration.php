<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

/**
 * Which generation of ADMS a device speaks. Negotiated per device at
 * registration, not a global setting (see CONTEXT.md "Protocol generation").
 *
 * `Legacy` is the only generation v1 acts on; `PushV2`/`PushV3` are recorded so
 * later milestones can branch parser/builder strategy on them.
 */
enum ProtocolGeneration: string
{
    case Legacy = 'Legacy';
    case PushV2 = 'PushV2';
    case PushV3 = 'PushV3';

    /**
     * Map the handshake's `pushver` parameter onto a generation. Absent or
     * unrecognised means the device speaks the legacy `cdata` text protocol.
     */
    public static function fromPushVersion(?string $pushver): self
    {
        if ($pushver === null || $pushver === '') {
            return self::Legacy;
        }

        return match (true) {
            str_starts_with($pushver, '3') => self::PushV3,
            str_starts_with($pushver, '2') => self::PushV2,
            default => self::Legacy,
        };
    }
}
