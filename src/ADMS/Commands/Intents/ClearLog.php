<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Clear the attendance transaction log, leaving users and templates in place.
 */
final class ClearLog implements DeviceCommand {}
