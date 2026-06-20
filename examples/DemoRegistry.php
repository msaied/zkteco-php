<?php

declare(strict_types=1);

/*
 * A tiny PDO/SQLite-backed DeviceRegistry for the ADMS demo scripts.
 *
 * It implements the package's real DeviceRegistry interface with no Laravel and
 * no Eloquent — which doubles as a demonstration that the framework-neutral core
 * (see docs/adr/0008) is usable standalone. The Laravel bridge's
 * EloquentDeviceRegistry is the production equivalent.
 *
 * Defaults to the "open" posture: any device is admitted and recorded, unknown
 * ones land pending until approved.
 */

require_once __DIR__.'/../vendor/autoload.php';

use ZkTeco\ADMS\Registry\Capabilities;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\ADMS\Registry\Stamp;

final class DemoRegistry implements DeviceRegistry
{
    private PDO $pdo;

    /**
     * @param  list<string>  $allowedSerials  approved on first contact
     */
    public function __construct(
        string $databasePath,
        private bool $autoRegister = true,
        private array $allowedSerials = [],
    ) {
        $this->pdo = new PDO('sqlite:'.$databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS devices (
                serial TEXT PRIMARY KEY, generation TEXT, status TEXT, last_seen TEXT, stamps TEXT
            )'
        );
    }

    public function admits(string $serialNumber): bool
    {
        $row = $this->row($serialNumber);

        if ($row !== null) {
            return $row['status'] !== DeviceStatus::Blocked->value;
        }

        return $this->autoRegister || in_array($serialNumber, $this->allowedSerials, true);
    }

    public function find(string $serialNumber): ?RegisteredDevice
    {
        $row = $this->row($serialNumber);

        return $row === null ? null : $this->toValue($row);
    }

    public function register(RegisteredDevice $device): RegisteredDevice
    {
        if ($this->row($device->serialNumber) === null) {
            $status = in_array($device->serialNumber, $this->allowedSerials, true)
                ? DeviceStatus::Approved
                : DeviceStatus::Pending;

            $this->pdo->prepare('INSERT INTO devices(serial, generation, status, last_seen, stamps) VALUES(?, ?, ?, ?, ?)')
                ->execute([$device->serialNumber, $device->generation->value, $status->value, date('c'), '{}']);
        } else {
            $this->pdo->prepare('UPDATE devices SET generation = ?, last_seen = ? WHERE serial = ?')
                ->execute([$device->generation->value, date('c'), $device->serialNumber]);
        }

        return $this->find($device->serialNumber) ?? $device;
    }

    public function markSeen(string $serialNumber): void
    {
        $this->pdo->prepare('UPDATE devices SET last_seen = ? WHERE serial = ?')->execute([date('c'), $serialNumber]);
    }

    public function updateStamp(string $serialNumber, Stamp $stamp): void
    {
        $row = $this->row($serialNumber);

        if ($row === null) {
            return;
        }

        $stamps = json_decode($row['stamps'] ?: '{}', true);
        $stamps[$stamp->table] = $stamp->value;
        $this->pdo->prepare('UPDATE devices SET stamps = ? WHERE serial = ?')
            ->execute([json_encode($stamps), $serialNumber]);
    }

    public function approve(string $serialNumber): void
    {
        $this->setStatus($serialNumber, DeviceStatus::Approved);
    }

    public function block(string $serialNumber): void
    {
        $this->setStatus($serialNumber, DeviceStatus::Blocked);
    }

    /**
     * @return list<array{serial: string, status: string, generation: string, last_seen: string}>
     */
    public function all(): array
    {
        /** @var list<array{serial: string, status: string, generation: string, last_seen: string}> $rows */
        $rows = $this->pdo->query('SELECT * FROM devices ORDER BY serial')->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    private function setStatus(string $serialNumber, DeviceStatus $status): void
    {
        $this->pdo->prepare('UPDATE devices SET status = ? WHERE serial = ?')->execute([$status->value, $serialNumber]);
    }

    /**
     * @return array<string, string>|null
     */
    private function row(string $serialNumber): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM devices WHERE serial = ?');
        $statement->execute([$serialNumber]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function toValue(array $row): RegisteredDevice
    {
        $stamps = [];
        foreach (json_decode($row['stamps'] ?: '{}', true) as $table => $value) {
            $stamps[$table] = new Stamp($table, (string) $value);
        }

        return new RegisteredDevice(
            serialNumber: $row['serial'],
            generation: ProtocolGeneration::tryFrom($row['generation']) ?? ProtocolGeneration::Legacy,
            capabilities: new Capabilities,
            lastSeenAt: null,
            stamps: $stamps,
            status: DeviceStatus::tryFrom($row['status']) ?? DeviceStatus::Pending,
        );
    }
}
