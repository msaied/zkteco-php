<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;
use ZkTeco\Values\AttendanceRecord;

/**
 * Decodes the body of a PUSH-SDK `RTLOG` upload — the real-time transaction log —
 * into {@see AttendanceRecord} values.
 *
 * `RTLOG` is the PUSH-SDK generation's realtime attendance channel, the superset
 * counterpart to legacy `ATTLOG`. An attendance row carries the same logical
 * fields — the PIN, the moment, the punch state and how identity was verified:
 *
 *     PIN \t YYYY-MM-DD HH:MM:SS \t status \t verify \t reserved…
 *
 * It is parsed as its own table, not folded into {@see AttlogParser}, because the
 * generations are pinned independently. Both adapters' attendance flows to the
 * one {@see AttendanceSink}, keeping the read path unified (see
 * docs/adr/0009).
 *
 * `RTLOG` also carries non-attendance events on some firmwares (door, alarm); a
 * row whose columns do not yield a PIN and a timestamp is dropped rather than
 * guessed at. The field order is firmware-sensitive and provisional until pinned
 * against a real capture (see docs/adr/0005): the parser anchors the punch
 * state/verify columns on the unambiguous timestamp rather than fixed offsets, so
 * re-pinning is a small change, not a rewrite.
 */
final class RtlogParser
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
        $timeIndex = $this->locateTimestamp($fields);

        if ($userId === '' || $timeIndex === null) {
            return null;
        }

        $recordedAt = $this->parseTimestamp($fields[$timeIndex]);

        if ($recordedAt === null) {
            return null;
        }

        return new AttendanceRecord(
            userId: $userId,
            recordedAt: $recordedAt,
            // The status and verify columns follow the timestamp in an RTLOG row;
            // anchoring on it rather than a fixed offset tolerates a leading
            // event-type column that some firmwares prepend.
            verifyMode: VerifyMode::fromWire($this->intField($fields, $timeIndex + 2, -1)),
            punchState: PunchState::tryFrom($this->intField($fields, $timeIndex + 1, 255)) ?? PunchState::Undefined,
            uid: null,
        );
    }

    /**
     * The index of the first field after the PIN that reads as a timestamp, or
     * null if none does.
     *
     * @param  list<string>  $fields
     */
    private function locateTimestamp(array $fields): ?int
    {
        foreach ($fields as $index => $field) {
            if ($index > 0 && $this->parseTimestamp($field) !== null) {
                return $index;
            }
        }

        return null;
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
