<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\Values\OperationLog;
use ZkTeco\Values\User;

/**
 * What an {@see OperlogParser} pass yields: the two record kinds a legacy
 * `OPERLOG` upload multiplexes together — operation log entries and the User
 * records the device syncs up (its USERINFO). Kept apart because each flows to
 * its own sink.
 */
final readonly class OperlogBatch
{
    /**
     * @param  list<OperationLog>  $operations
     * @param  list<User>  $users
     */
    public function __construct(
        public array $operations = [],
        public array $users = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->operations === [] && $this->users === [];
    }

    public function count(): int
    {
        return count($this->operations) + count($this->users);
    }
}
