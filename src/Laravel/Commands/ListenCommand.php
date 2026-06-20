<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use ZkTeco\Exceptions\NetworkException;
use ZkTeco\Laravel\DeviceManager;
use ZkTeco\Laravel\Events\PunchReceived;

/**
 * Long-running daemon that streams live punches from a device and dispatches a
 * {@see PunchReceived} event for each one. Supervise this (e.g. with Horizon or
 * systemd) so it restarts after the connection drops.
 */
final class ListenCommand extends Command
{
    protected $signature = 'zkteco:listen {connection? : The configured device connection name}';

    protected $description = 'Stream live punches from a ZKTeco device as PunchReceived events.';

    public function handle(DeviceManager $devices): int
    {
        $connection = $devices->resolveName($this->argument('connection'));

        try {
            $device = $devices->connection($connection);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Listening to ZKTeco device [{$device->host}] on connection [{$connection}]...");

        $listening = true;
        $this->trapStopSignals(function () use (&$listening): void {
            $listening = false;
        });

        $device->connect();

        try {
            foreach ($device->realtime()->live() as $record) {
                if (! $listening) {
                    break;
                }

                // The stream yields null on an idle timeout so we can react to a
                // stop signal between punches; there is nothing to dispatch.
                if ($record === null) {
                    continue;
                }

                event(new PunchReceived($record, $connection));

                $this->line("  {$record->recordedAt->format('Y-m-d H:i:s')}  user {$record->userId}  {$record->punchState->name}");
            }
        } catch (NetworkException $exception) {
            $this->error("Connection to [{$device->host}] dropped: {$exception->getMessage()}");

            return self::FAILURE;
        } finally {
            $device->disconnect();
        }

        $this->info('Stopped listening.');

        return self::SUCCESS;
    }

    /**
     * Register a graceful-stop handler for SIGINT/SIGTERM when signal handling
     * is available (it needs the pcntl extension). A no-op otherwise.
     *
     * @param  callable():void  $onStop
     */
    private function trapStopSignals(callable $onStop): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        $this->trap([SIGINT, SIGTERM], static function () use ($onStop): void {
            $onStop();
        });
    }
}
