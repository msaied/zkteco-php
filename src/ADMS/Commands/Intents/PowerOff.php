<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Power the device off — the socket client's `control()->powerOff()` counterpart.
 *
 * ADMS has no canonical power-off verb, so its rendering is a best-effort,
 * provisional instruction pending a real capture (see docs/adr/0005); M6
 * confirms or drops it.
 */
final class PowerOff implements DeviceCommand {}
