<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Commands;

use Illuminate\Console\Command;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\Laravel\Models\Device;

/**
 * Approve or block an ADMS device by serial number — the "add this device"
 * (or "refuse it") action behind the open-registration posture. Until a pending
 * device is approved, its attendance is held rather than ingested.
 */
final class ApproveDeviceCommand extends Command
{
    protected $signature = 'zkteco:approve {serial : The device serial number} {--block : Block the device instead of approving it}';

    protected $description = 'Approve (or --block) an ADMS device so its attendance is ingested or refused.';

    public function handle(DeviceRegistry $registry): int
    {
        /** @var string $serial */
        $serial = $this->argument('serial');

        if (Device::query()->where('serial_number', $serial)->doesntExist()) {
            $this->error("No ADMS device registered with serial [{$serial}].");

            return self::FAILURE;
        }

        if ($this->option('block')) {
            $registry->block($serial);
            $this->info("Blocked device [{$serial}]. It will be rejected on its next request.");

            return self::SUCCESS;
        }

        $registry->approve($serial);
        $this->info("Approved device [{$serial}]. Its attendance will be ingested from its next upload.");

        return self::SUCCESS;
    }
}
