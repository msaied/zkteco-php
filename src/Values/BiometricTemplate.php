<?php

declare(strict_types=1);

namespace ZkTeco\Values;

/**
 * A biometric enrollment a device uploads over ADMS PUSH SDK's `BIODATA` table.
 *
 * This is the ADMS counterpart to {@see Template} and is deliberately *not* the
 * same value: a `BIODATA` row is keyed by the human-facing PIN (`userId`), not by
 * a device-local record slot, and it names the biometric `type` rather than a
 * fingerprint index. The device-local `uid` is never present in a push row, so it
 * is not modeled here (see CONTEXT.md "Template" / "User").
 *
 * `type` is the raw biometric-type code as the device sends it — 1 = fingerprint,
 * 2 = face, 9 = palm are the widely-seen values, but the table is firmware-
 * sensitive and provisional until pinned against a real capture (see
 * docs/adr/0005), so it is carried as a plain int rather than hardened into an
 * enum. `data` is the opaque template payload, uninterpreted by this package.
 */
final readonly class BiometricTemplate
{
    public function __construct(
        public string $userId,
        public int $type,
        public int $index,
        public bool $valid,
        public string $data,
    ) {}
}
