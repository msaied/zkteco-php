<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Clear the stored attendance photos, leaving users, templates, and the
 * transaction log in place.
 */
final class ClearPhoto implements DeviceCommand {}
