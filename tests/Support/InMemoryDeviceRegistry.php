<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use DateTimeImmutable;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\ADMS\Registry\Stamp;

/**
 * An in-memory {@see DeviceRegistry} for core tests, mirroring the bridge's
 * policy: allowlisted serials approve on sight, others land pending (or are
 * refused admission in strict mode). No database, no Laravel.
 */
final class InMemoryDeviceRegistry implements DeviceRegistry
{
    /** @var array<string, RegisteredDevice> */
    public array $devices = [];

    /** @var list<string> */
    public array $seen = [];

    /**
     * @param  list<string>  $allowedSerials  serials approved on first contact
     * @param  bool  $autoRegister  admit and record unknown serials as pending
     */
    public function __construct(
        private array $allowedSerials = [],
        private bool $autoRegister = false,
    ) {}

    public function admits(string $serialNumber): bool
    {
        $device = $this->devices[$serialNumber] ?? null;

        if ($device !== null) {
            return $device->status !== DeviceStatus::Blocked;
        }

        return $this->autoRegister || in_array($serialNumber, $this->allowedSerials, true);
    }

    public function find(string $serialNumber): ?RegisteredDevice
    {
        return $this->devices[$serialNumber] ?? null;
    }

    public function register(RegisteredDevice $device): RegisteredDevice
    {
        $existing = $this->devices[$device->serialNumber] ?? null;

        $status = $existing?->status
            ?? (in_array($device->serialNumber, $this->allowedSerials, true)
                ? DeviceStatus::Approved
                : DeviceStatus::Pending);

        return $this->devices[$device->serialNumber] = new RegisteredDevice(
            serialNumber: $device->serialNumber,
            generation: $device->generation,
            capabilities: $device->capabilities,
            lastSeenAt: new DateTimeImmutable('2026-01-01 00:00:00'),
            stamps: $existing->stamps ?? $device->stamps,
            status: $status,
        );
    }

    public function markSeen(string $serialNumber): void
    {
        $this->seen[] = $serialNumber;
    }

    public function updateStamp(string $serialNumber, Stamp $stamp): void
    {
        $device = $this->devices[$serialNumber] ?? null;

        if ($device === null) {
            return;
        }

        $stamps = $device->stamps;
        $stamps[$stamp->table] = $stamp;

        $this->devices[$serialNumber] = $this->withStamps($device, $stamps);
    }

    public function approve(string $serialNumber): void
    {
        $this->setStatus($serialNumber, DeviceStatus::Approved);
    }

    public function block(string $serialNumber): void
    {
        $this->setStatus($serialNumber, DeviceStatus::Blocked);
    }

    private function setStatus(string $serialNumber, DeviceStatus $status): void
    {
        $device = $this->devices[$serialNumber] ?? null;

        if ($device === null) {
            return;
        }

        $this->devices[$serialNumber] = new RegisteredDevice(
            serialNumber: $device->serialNumber,
            generation: $device->generation,
            capabilities: $device->capabilities,
            lastSeenAt: $device->lastSeenAt,
            stamps: $device->stamps,
            status: $status,
        );
    }

    /**
     * @param  array<string, Stamp>  $stamps
     */
    private function withStamps(RegisteredDevice $device, array $stamps): RegisteredDevice
    {
        return new RegisteredDevice(
            serialNumber: $device->serialNumber,
            generation: $device->generation,
            capabilities: $device->capabilities,
            lastSeenAt: $device->lastSeenAt,
            stamps: $stamps,
            status: $device->status,
        );
    }
}
