<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Restart the device — the socket client's `control()->restart()` counterpart.
 * ADMS has no distinct restart verb, so this renders to the same instruction as
 * {@see Reboot}.
 */
final class Restart implements DeviceCommand {}
