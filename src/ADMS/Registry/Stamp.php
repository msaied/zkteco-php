<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

/**
 * A per-table, per-device watermark marking how far a device has uploaded a
 * given table (see CONTEXT.md "Stamp"). The device resumes from its last stamp,
 * so the server must persist it.
 *
 * The value is treated as opaque text — it is the device's cursor, echoed back,
 * never arithmetic we perform.
 */
final readonly class Stamp
{
    public function __construct(
        public string $table,
        public string $value,
    ) {}
}
