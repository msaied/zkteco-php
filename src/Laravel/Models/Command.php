<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ZkTeco\ADMS\Commands\CommandStatus;

/**
 * Persistence for an outbound ADMS command — one row per instruction enqueued
 * for a Registered device, keyed by the autoincrement `id` the device echoes
 * back as `ID=` when it reports the result.
 *
 * The row's lifecycle mirrors {@see CommandStatus}: enqueued Pending, flipped to
 * Sent when drained on a poll, then Acknowledged with the device's `return_code`.
 * Publish and run the package migrations to create its table.
 *
 * @property int $id
 * @property string $serial_number
 * @property string $command
 * @property CommandStatus $status
 * @property int|null $return_code
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 */
class Command extends Model
{
    protected $table = 'zkteco_commands';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommandStatus::class,
            'return_code' => 'integer',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }
}
