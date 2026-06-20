<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ZkTeco\ADMS\Registry\DeviceStatus;

/**
 * Persistence for an ADMS Registered device — one row per serial number.
 *
 * This is the bridge's record of devices that have dialed in over ADMS; it is
 * unrelated to the core {@see \ZkTeco\TCP\Device}, which is the client-initiated ZK
 * socket entry point. Publish and run the package migrations to create its
 * table.
 *
 * @property string $serial_number
 * @property string $protocol_generation
 * @property DeviceStatus $status
 * @property array<string, mixed>|null $capabilities
 * @property array<string, string>|null $stamps
 * @property Carbon|null $last_seen_at
 */
class Device extends Model
{
    protected $table = 'zkteco_devices';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'capabilities' => 'array',
            'stamps' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }
}
