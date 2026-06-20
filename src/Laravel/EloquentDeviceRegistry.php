<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use Illuminate\Support\Facades\Date;
use ZkTeco\ADMS\Registry\Capabilities;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\ADMS\Registry\Stamp;
use ZkTeco\Laravel\Events\DeviceRegistered;
use ZkTeco\Laravel\Models\Device;

/**
 * Eloquent-backed {@see DeviceRegistry}: policy comes from config, device
 * records live in `zkteco_devices` (see docs/adr/0008).
 *
 * Two postures, both trust-but-gate (see docs/adr/0010):
 *
 * - **Strict** (`autoRegister = false`, the default): only serials on the
 *   allowlist are admitted, and they are approved on sight. Everything else is
 *   rejected and never recorded.
 * - **Open** (`autoRegister = true`): any device is admitted and recorded, but
 *   an unknown one lands as {@see DeviceStatus::Pending} — visible, yet its
 *   attendance is held until an operator calls {@see approve()}. Allowlisted
 *   serials are still approved on sight.
 *
 * In both postures, recording a device is never the same as trusting its data.
 */
final class EloquentDeviceRegistry implements DeviceRegistry
{
    /**
     * @param  list<string>  $allowedSerials  serials approved on first contact
     * @param  bool  $autoRegister  admit and record unknown serials as pending
     */
    public function __construct(
        private array $allowedSerials,
        private bool $autoRegister = false,
    ) {}

    public function admits(string $serialNumber): bool
    {
        $row = Device::query()->where('serial_number', $serialNumber)->first();

        if ($row !== null) {
            return $row->status !== DeviceStatus::Blocked;
        }

        return $this->autoRegister || $this->isAllowlisted($serialNumber);
    }

    public function find(string $serialNumber): ?RegisteredDevice
    {
        $row = Device::query()->where('serial_number', $serialNumber)->first();

        return $row === null ? null : $this->toValue($row);
    }

    public function register(RegisteredDevice $device): RegisteredDevice
    {
        $row = Device::query()->firstOrNew(['serial_number' => $device->serialNumber]);
        $isNew = ! $row->exists;

        $row->protocol_generation = $device->generation->value;
        $row->capabilities = $this->capabilitiesToArray($device->capabilities);
        $row->last_seen_at = Date::now();

        if ($isNew) {
            $row->stamps = [];
            $row->status = $this->isAllowlisted($device->serialNumber)
                ? DeviceStatus::Approved
                : DeviceStatus::Pending;
        }

        $row->save();

        $registered = $this->toValue($row);

        if ($isNew) {
            event(new DeviceRegistered($registered));
        }

        return $registered;
    }

    public function markSeen(string $serialNumber): void
    {
        Device::query()
            ->where('serial_number', $serialNumber)
            ->update(['last_seen_at' => Date::now()]);
    }

    public function updateStamp(string $serialNumber, Stamp $stamp): void
    {
        $row = Device::query()->where('serial_number', $serialNumber)->first();

        if ($row === null) {
            return;
        }

        $stamps = $row->stamps ?? [];
        $stamps[$stamp->table] = $stamp->value;
        $row->stamps = $stamps;
        $row->save();
    }

    public function approve(string $serialNumber): void
    {
        Device::query()
            ->where('serial_number', $serialNumber)
            ->update(['status' => DeviceStatus::Approved]);
    }

    public function block(string $serialNumber): void
    {
        Device::query()
            ->where('serial_number', $serialNumber)
            ->update(['status' => DeviceStatus::Blocked]);
    }

    private function isAllowlisted(string $serialNumber): bool
    {
        return in_array($serialNumber, $this->allowedSerials, true);
    }

    private function toValue(Device $row): RegisteredDevice
    {
        $stamps = [];

        foreach ($row->stamps ?? [] as $table => $value) {
            $stamps[$table] = new Stamp($table, (string) $value);
        }

        return new RegisteredDevice(
            serialNumber: $row->serial_number,
            generation: ProtocolGeneration::tryFrom($row->protocol_generation) ?? ProtocolGeneration::Legacy,
            capabilities: $this->capabilitiesFromArray($row->capabilities ?? []),
            lastSeenAt: $row->last_seen_at?->toDateTimeImmutable(),
            stamps: $stamps,
            status: $row->status ?? DeviceStatus::Pending,
        );
    }

    /**
     * @return array{fingerprint: bool, face: bool, userPhoto: bool, raw: array<string, string>}
     */
    private function capabilitiesToArray(Capabilities $capabilities): array
    {
        return [
            'fingerprint' => $capabilities->fingerprint,
            'face' => $capabilities->face,
            'userPhoto' => $capabilities->userPhoto,
            'raw' => $capabilities->raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function capabilitiesFromArray(array $stored): Capabilities
    {
        /** @var array<string, string> $raw */
        $raw = $stored['raw'] ?? [];

        return new Capabilities(
            fingerprint: (bool) ($stored['fingerprint'] ?? false),
            face: (bool) ($stored['face'] ?? false),
            userPhoto: (bool) ($stored['userPhoto'] ?? false),
            raw: $raw,
        );
    }
}
