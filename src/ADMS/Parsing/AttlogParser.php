<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;
use ZkTeco\Values\AttendanceRecord;

/**
 * Decodes the body of an `ATTLOG` upload into {@see AttendanceRecord} values.
 *
 * Legacy ADMS posts attendance as newline-separated rows of tab-separated
 * fields:
 *
 *     PIN \t YYYY-MM-DD HH:MM:SS \t status \t verify \t workcode \t reserved…
 *
 * where PIN is the human-facing user id, `status` is the punch state and
 * `verify` is how identity was confirmed. Only PIN and the timestamp are
 * required; a row missing either is skipped rather than guessed at.
 *
 * The field order and the `verify` code table here follow the published legacy
 * format and reuse {@see VerifyMode::fromWire()}, but both are firmware-sensitive
 * and provisional until pinned against a real capture from target hardware (see
 * docs/adr/0005 and the implementation plan's capture-first step). The parser is
 * deliberately tolerant so that re-pinning is a one-line change, not a rewrite.
 */
final class AttlogParser
{
    use ParsesAdmsRows;

    /**
     * @return list<AttendanceRecord>
     */
    public function parse(string $body): array
    {
        $records = [];

        foreach ($this->splitRows($body) as $line) {
            $record = $this->parseLine($line);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function parseLine(string $line): ?AttendanceRecord
    {
        $fields = explode("\t", $line);

        $userId = trim($fields[0] ?? '');
        $recordedAt = $this->parseTimestamp($fields[1] ?? '');

        if ($userId === '' || $recordedAt === null) {
            return null;
        }

        return new AttendanceRecord(
            userId: $userId,
            recordedAt: $recordedAt,
            verifyMode: VerifyMode::fromWire($this->intField($fields, 3, -1)),
            punchState: PunchState::tryFrom($this->intField($fields, 2, 255)) ?? PunchState::Undefined,
            uid: null,
        );
    }

    /**
     * @param  list<string>  $fields
     */
    private function intField(array $fields, int $index, int $default): int
    {
        $value = trim($fields[$index] ?? '');

        return $value === '' ? $default : (int) $value;
    }
}
