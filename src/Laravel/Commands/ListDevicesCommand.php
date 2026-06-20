<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Commands;

use Illuminate\Console\Command;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\Laravel\Models\Device;

/**
 * List the ADMS devices that have dialed in, so an operator can see which are
 * pending approval. Pass `--pending` to show only those awaiting a decision.
 */
final class ListDevicesCommand extends Command
{
    protected $signature = 'zkteco:devices {--pending : Show only devices awaiting approval}';

    protected $description = 'List ADMS devices that have registered, with their approval status.';

    public function handle(): int
    {
        $query = Device::query()->orderBy('serial_number');

        if ($this->option('pending')) {
            $query->where('status', DeviceStatus::Pending);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->info('No ADMS devices registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Serial', 'Status', 'Generation', 'Last seen'],
            $devices->map(fn (Device $device): array => [
                $device->serial_number,
                $device->status->value,
                $device->protocol_generation,
                $device->last_seen_at?->diffForHumans() ?? 'never',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
