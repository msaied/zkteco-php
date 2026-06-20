<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Re-enable a disabled device — the socket client's `control()->enable()`
 * counterpart.
 *
 * ADMS has no canonical enable verb, so its rendering is a best-effort,
 * provisional instruction pending a real capture (see docs/adr/0005); M6
 * confirms or drops it.
 */
final class Enable implements DeviceCommand {}
