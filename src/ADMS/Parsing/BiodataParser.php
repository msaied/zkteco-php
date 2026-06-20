<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\Values\BiometricTemplate;

/**
 * Decodes the body of a PUSH-SDK `BIODATA` upload — the biometric template
 * channel — into {@see BiometricTemplate} values.
 *
 * `BIODATA` is a PUSH-SDK table with no legacy equivalent: the legacy generation
 * leaves its `FP`/`FACE` rows undecoded inside the `OPERLOG` stream (see
 * docs/adr/0011), whereas PUSH SDK uploads each enrollment as its own row of
 * order-independent `Key=Value` pairs, optionally led by a `BIODATA` tag word:
 *
 *     BIODATA Pin=1 \t No=0 \t Index=0 \t Valid=1 \t Type=1 \t Tmp=<base64…>
 *
 * The row is keyed by the PIN, names the biometric `Type`, and carries an opaque
 * template blob (`Tmp`). Only the PIN and the blob are required; a row missing
 * either is dropped rather than invented. A bare leading tag word carries no `=`,
 * so it is simply ignored by the pair reader.
 *
 * The key set is firmware-sensitive and provisional until pinned against a real
 * capture (see docs/adr/0005); parsing is deliberately tolerant so re-pinning is
 * a small change.
 */
final class BiodataParser
{
    use ParsesAdmsRows;

    /**
     * @return list<BiometricTemplate>
     */
    public function parse(string $body): array
    {
        $templates = [];

        foreach ($this->splitRows($body) as $line) {
            $template = $this->parseLine($line);

            if ($template !== null) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    private function parseLine(string $line): ?BiometricTemplate
    {
        $pairs = $this->pairs($line);

        $userId = $pairs['pin'] ?? '';
        $data = $pairs['tmp'] ?? $pairs['tmpdata'] ?? '';

        if ($userId === '' || $data === '') {
            return null;
        }

        return new BiometricTemplate(
            // Keyed by PIN; a push row never carries the device-local slot, so the
            // template stands on the employee number alone (see CONTEXT.md).
            userId: $userId,
            type: (int) ($pairs['type'] ?? '0'),
            // Firmwares disagree on the slot-within-user column name; both the
            // explicit Index and the legacy No are honoured.
            index: (int) ($pairs['index'] ?? $pairs['no'] ?? '0'),
            valid: ($pairs['valid'] ?? '1') !== '0',
            data: $data,
        );
    }

    /**
     * Split a row into lower-cased keys mapped to their values. An optional
     * leading tag word (`BIODATA`), separated from the first pair by a space or a
     * tab, is dropped; the pairs themselves are tab-separated.
     *
     * @return array<string, string>
     */
    private function pairs(string $line): array
    {
        $parts = preg_split('/[\t ]+/', $line, 2);
        $first = $parts[0] ?? '';
        // A leading word with no `=` is a bare tag, not a pair; skip past it.
        $payload = str_contains($first, '=') ? $line : ($parts[1] ?? '');

        $pairs = [];

        foreach (explode("\t", $payload) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $pairs[strtolower(trim($key))] = trim($value);
        }

        return $pairs;
    }
}
