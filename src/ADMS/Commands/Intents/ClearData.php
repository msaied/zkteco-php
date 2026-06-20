<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Clear all data on the device — users, templates, and logs — the socket
 * client's `control()->clearData()` counterpart.
 */
final class ClearData implements DeviceCommand {}
