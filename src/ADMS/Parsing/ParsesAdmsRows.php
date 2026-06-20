<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use DateTimeImmutable;

/**
 * The row-level primitives shared by the ADMS text-table parsers. The newline
 * convention and the wall-clock timestamp format are fixed by the ADMS text
 * protocol — not per-table — so they live here once rather than being re-derived
 * in each parser.
 */
trait ParsesAdmsRows
{
    /**
     * Split an upload body into its non-empty, trimmed rows. ADMS bodies are
     * newline-separated and may arrive with any of the three line endings.
     *
     * @return list<string>
     */
    private function splitRows(string $body): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $rows[] = $line;
            }
        }

        return $rows;
    }

    /**
     * Parse an ADMS wall-clock timestamp (`YYYY-MM-DD HH:MM:SS`), or null when the
     * column is empty or malformed.
     */
    private function parseTimestamp(string $raw): ?DateTimeImmutable
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);

        return $parsed === false ? null : $parsed;
    }
}
