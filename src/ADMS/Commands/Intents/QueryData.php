<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Ask the device to re-upload a table — e.g. `USERINFO` to resync its user list,
 * or `FINGERTMP`/`BIODATA` to resync templates. The decoded rows arrive on the
 * normal read path, the same as an unsolicited upload.
 */
final readonly class QueryData implements DeviceCommand
{
    public function __construct(
        public string $table,
    ) {}
}
