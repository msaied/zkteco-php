<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

use DateTimeImmutable;

/**
 * An ADMS device that has dialed in and been recorded, keyed by its serial
 * number (see CONTEXT.md "Registered device").
 *
 * This is the ADMS counterpart to the ZK protocol's host/port `Device` — not
 * the same thing. The ZK protocol initiates to a host/port; an ADMS device
 * initiates to us and is known only by the serial number it announces, plus the
 * generation, capabilities, last-seen time, and per-table stamps gathered from
 * its traffic (see docs/adr/0009).
 */
final readonly class RegisteredDevice
{
    /**
     * @param  array<string, Stamp>  $stamps  keyed by table name
     */
    public function __construct(
        public string $serialNumber,
        public ProtocolGeneration $generation,
        public Capabilities $capabilities,
        public ?DateTimeImmutable $lastSeenAt = null,
        public array $stamps = [],
        public DeviceStatus $status = DeviceStatus::Pending,
    ) {}

    public function isApproved(): bool
    {
        return $this->status === DeviceStatus::Approved;
    }

    public function stampFor(string $table): ?Stamp
    {
        return $this->stamps[$table] ?? null;
    }
}
