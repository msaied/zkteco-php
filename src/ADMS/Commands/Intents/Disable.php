<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Put the device out of service — the socket client's `control()->disable()`
 * counterpart.
 *
 * ADMS has no canonical disable verb, so its rendering is a best-effort,
 * provisional instruction pending a real capture (see docs/adr/0005); M6
 * confirms or drops it.
 */
final class Disable implements DeviceCommand {}
