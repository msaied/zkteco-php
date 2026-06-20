<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Restart the device. ADMS exposes a single reboot verb, so {@see Restart} maps
 * to the same instruction.
 */
final class Reboot implements DeviceCommand {}
