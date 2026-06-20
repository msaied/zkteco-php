<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

/**
 * Where Registered devices live: the allowlist gate plus the record of every
 * device that has dialed in.
 *
 * This is a core interface; persistence belongs to the bridge (see
 * docs/adr/0008). It keeps the package trust-but-gate rather than
 * trust-on-first-sight (see docs/adr/0010): admission is separate from approval,
 * so a device can be recorded without its data being trusted.
 */
interface DeviceRegistry
{
    /**
     * Whether this serial number may talk to us at all. A blocked device, or in
     * strict mode an un-allowlisted one, returns `false` and is rejected at the
     * door. Admitting a device is not the same as approving it — an admitted but
     * unapproved device registers, but its attendance is held (see
     * {@see DeviceStatus}).
     */
    public function admits(string $serialNumber): bool;

    public function find(string $serialNumber): ?RegisteredDevice;

    /**
     * Record a device at handshake (or refresh it on re-handshake) and return
     * the stored view, including its server-assigned last-seen time and approval
     * status. The status of an already-registered device is preserved.
     */
    public function register(RegisteredDevice $device): RegisteredDevice;

    /**
     * Note that an already-registered device made contact, without otherwise
     * changing it. A no-op for a serial that has not registered yet.
     */
    public function markSeen(string $serialNumber): void;

    public function updateStamp(string $serialNumber, Stamp $stamp): void;

    /**
     * Admit a pending device's data: flip it to {@see DeviceStatus::Approved} so
     * its attendance begins flowing. This is the "add this device" action.
     */
    public function approve(string $serialNumber): void;

    /**
     * Refuse a device outright: flip it to {@see DeviceStatus::Blocked} so it is
     * rejected on every subsequent request.
     */
    public function block(string $serialNumber): void;
}
