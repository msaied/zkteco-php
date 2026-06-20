<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\ADMS\Commands\CommandResult;

/**
 * Parses the body a device POSTs to `/iclock/devicecmd` after running queued
 * commands — one result per line, each a set of `Key=Value` pairs joined by `&`,
 * e.g. `ID=12&Return=0&CMD=DATA`.
 *
 * The exact field set is firmware-sensitive (see docs/adr/0005), so parsing is
 * deliberately tolerant: it reads `ID`/`Return`/`CMD` case-insensitively, skips
 * lines without an `ID`, and treats a missing or non-numeric `Return` as a
 * failure rather than a silent success.
 */
final class CommandResultParser
{
    /**
     * @return list<CommandResult>
     */
    public function parse(string $body): array
    {
        $results = [];

        foreach (preg_split('/\R/', trim($body)) ?: [] as $line) {
            $fields = $this->fields(trim($line));
            $id = $fields['id'] ?? '';

            if ($id === '') {
                continue;
            }

            $results[] = new CommandResult(
                id: $id,
                returnCode: isset($fields['return']) && is_numeric($fields['return'])
                    ? (int) $fields['return']
                    : -1,
                command: $fields['cmd'] ?? '',
            );
        }

        return $results;
    }

    /**
     * Split one result line into lower-cased keys mapped to their values.
     *
     * @return array<string, string>
     */
    private function fields(string $line): array
    {
        $fields = [];

        foreach (explode('&', $line) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $fields[strtolower(trim($key))] = trim($value);
        }

        return $fields;
    }
}
