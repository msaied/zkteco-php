<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Generations;

/**
 * The result of a {@see Generation} decoding one upload table: whether the table
 * was one this generation owns, and how many records it ingested.
 *
 * This is the seam that lets a generation route heterogeneous decoded values
 * (attendance, operation logs, users, photos, biometrics) to their own sinks
 * while handing the handler back a single uniform answer. The handler maps
 * `handled` onto whether to advance the device's stamp, and `count` onto the
 * `OK: <n>` reply — without ever knowing what a row decodes into.
 */
final readonly class IngestOutcome
{
    private function __construct(
        public bool $handled,
        public int $count,
    ) {}

    /**
     * The table was decoded; `$count` rows were ingested.
     */
    public static function of(int $count): self
    {
        return new self(true, $count);
    }

    /**
     * The table is not one this generation owns; it was left untouched.
     */
    public static function ignored(): self
    {
        return new self(false, 0);
    }
}
