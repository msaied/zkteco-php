<?php

declare(strict_types=1);

namespace ZkTeco\Values;

use DateTimeImmutable;
use ZkTeco\Enums\OperationType;

/**
 * A single entry from a device's operation log: something an operator did on the
 * device — enrolling or deleting a User, changing a setting, a power cycle.
 *
 * The ADMS counterpart to the audit trail the ZK protocol exposes only as live
 * events. `operatorId` is the admin who performed the action and `target` the
 * thing acted on (typically the affected User's id), both as the device reports
 * them. `code` is the raw operation code; `operation` is its interpretation,
 * which is provisional (see {@see OperationType}). Any trailing values the device
 * sends are preserved untouched in `parameters`.
 */
final readonly class OperationLog
{
    /**
     * @param  list<string>  $parameters  trailing operation values, uninterpreted
     */
    public function __construct(
        public OperationType $operation,
        public int $code,
        public string $operatorId,
        public DateTimeImmutable $occurredAt,
        public string $target = '',
        public array $parameters = [],
    ) {}
}
