<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

use ZkTeco\ADMS\Commands\Intents\Reboot;
use ZkTeco\ADMS\Generations\Generation;

/**
 * A typed instruction to send a Registered device — what the caller wants done
 * ({@see Reboot}, delete a user, push a template), carried as a value with no
 * wire syntax of its own.
 *
 * Rendering an intent to its ADMS command string is the {@see Generation}'s job,
 * not the intent's: the same {@see Reboot} becomes one string on a legacy device
 * and may become another on PUSH SDK, so the syntax lives with the generation
 * that owns the protocol, while the intent stays a pure, inspectable value (see
 * docs/adr/0013). The {@see DeviceCommander} resolves the device's generation,
 * renders the intent, and enqueues the result.
 *
 * This is a sealed marker: every implementation lives in
 * {@see Intents} and every generation's renderer must have
 * an arm for it, or rendering throws.
 */
interface DeviceCommand {}
