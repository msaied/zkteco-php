<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\Values\OperationLog;

/**
 * Dispatched for each operation log entry an ADMS device uploads in an `OPERLOG`
 * batch — an operator enrolling or deleting a User, a settings change, a power
 * cycle. Listen for this to build an audit trail or react to device admin
 * activity.
 *
 * `$connection` is the device serial number, since that is how an ADMS device is
 * addressed.
 */
final class OperationLogged
{
    use Dispatchable;

    public function __construct(
        public readonly OperationLog $entry,
        public readonly string $connection,
    ) {}
}
