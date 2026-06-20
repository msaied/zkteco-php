<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\Enums\OperationType;
use ZkTeco\Enums\Privilege;
use ZkTeco\Values\OperationLog;
use ZkTeco\Values\User;

/**
 * Decodes the body of an `OPERLOG` upload — the device's audit channel.
 *
 * Unlike `ATTLOG`, an `OPERLOG` body is a *multiplexed* stream: each newline row
 * begins with a tag word naming its kind, and the legacy generation rides a
 * device's USERINFO in here too. Two kinds are decoded; the rest (`FP`, `FACE`,
 * `USERPIC`, `BIODATA` — biometric/photo rows that belong to a later milestone)
 * are skipped rather than guessed at:
 *
 *     OPLOG \t <code> \t <operator> \t <target> \t YYYY-MM-DD HH:MM:SS \t <param…>
 *     USER  PIN=… \t Name=… \t Pri=… \t Passwd=… \t Card=… \t Grp=…
 *
 * Both layouts are firmware-sensitive and provisional until pinned against a real
 * capture (see docs/adr/0005). Parsing is therefore deliberately tolerant: an
 * `OPLOG` row keys off the one unambiguous column — the timestamp — rather than a
 * fixed field count, and a `USER` row is read as order-independent `Key=Value`
 * pairs. A row that cannot yield its required fields is dropped, not invented.
 */
final class OperlogParser
{
    use ParsesAdmsRows;

    public function parse(string $body): OperlogBatch
    {
        $operations = [];
        $users = [];

        foreach ($this->splitRows($body) as $line) {
            [$tag, $rest] = $this->splitTag($line);

            if ($tag === 'OPLOG') {
                $operation = $this->parseOperation($rest);

                if ($operation !== null) {
                    $operations[] = $operation;
                }
            } elseif ($tag === 'USER') {
                $user = $this->parseUser($rest);

                if ($user !== null) {
                    $users[] = $user;
                }
            }
        }

        return new OperlogBatch($operations, $users);
    }

    /**
     * Split a row's leading tag word from its payload. The tag is separated by a
     * tab or a space, so the first run of either ends it.
     *
     * @return array{0: string, 1: string}
     */
    private function splitTag(string $line): array
    {
        $parts = preg_split('/[\t ]+/', $line, 2);
        $tag = strtoupper($parts[0] ?? '');

        return [$tag, $parts[1] ?? ''];
    }

    private function parseOperation(string $rest): ?OperationLog
    {
        $fields = explode("\t", $rest);
        $code = $this->intField($fields, 0);

        if ($code === null) {
            return null;
        }

        $timeIndex = $this->locateTimestamp($fields);

        if ($timeIndex === null) {
            return null;
        }

        $occurredAt = $this->parseTimestamp($fields[$timeIndex]);
        $parameters = array_values(array_filter(
            array_map('trim', array_slice($fields, $timeIndex + 1)),
            static fn (string $value): bool => $value !== '',
        ));

        return new OperationLog(
            operation: OperationType::fromCode($code),
            code: $code,
            operatorId: trim($fields[1] ?? ''),
            occurredAt: $occurredAt,
            // Any column between the operator and the timestamp is the object the
            // operation acted on; with none, the operation stands alone.
            target: $timeIndex > 2 ? trim($fields[2] ?? '') : '',
            parameters: $parameters,
        );
    }

    private function parseUser(string $rest): ?User
    {
        $pairs = $this->pairs($rest);
        $userId = $pairs['pin'] ?? '';

        if ($userId === '') {
            return null;
        }

        return new User(
            // A pushed USER row is keyed by PIN (the employee number); the device
            // does not send its local record slot, so uid is 0 ("unknown slot")
            // and must never be taken from the PIN (see CONTEXT.md).
            uid: 0,
            userId: $userId,
            name: $pairs['name'] ?? '',
            privilege: Privilege::tryFrom((int) ($pairs['pri'] ?? '0')) ?? Privilege::User,
            password: ($pairs['passwd'] ?? '') === '' ? null : $pairs['passwd'],
            cardNumber: ($pairs['card'] ?? '') === '' || ($pairs['card'] ?? '0') === '0' ? null : $pairs['card'],
            groupId: (int) ($pairs['grp'] ?? '0'),
        );
    }

    /**
     * The index of the first field that reads as a timestamp, or null if none
     * does. An `OPLOG` row is anchored on this rather than a field count.
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
     * Split a `USER` payload into lower-cased keys mapped to their values. Pairs
     * are tab-separated; a stray field without `=` is ignored.
     *
     * @return array<string, string>
     */
    private function pairs(string $rest): array
    {
        $pairs = [];

        foreach (explode("\t", $rest) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $pairs[strtolower(trim($key))] = trim($value);
        }

        return $pairs;
    }

    /**
     * @param  list<string>  $fields
     */
    private function intField(array $fields, int $index): ?int
    {
        $value = trim($fields[$index] ?? '');

        return is_numeric($value) ? (int) $value : null;
    }
}
