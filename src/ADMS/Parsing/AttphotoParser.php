<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Parsing;

use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\Values\AttendancePhoto;

/**
 * Decodes an `ATTPHOTO` upload into an {@see AttendancePhoto}.
 *
 * Unlike the text tables, this upload is mostly binary, and firmwares disagree on
 * where the metadata lives: some put the PIN and time in the query string and
 * POST the raw JPEG as the body, others lead the body with a tab-separated
 * `Key=Value` header line and follow it with the bytes. The parser tolerates
 * both — it reads `PIN`/`time` from the query first, falls back to a body header,
 * and treats whatever remains after the header (or the whole body, when it is a
 * bare JPEG) as the opaque image.
 *
 * The layout is firmware-sensitive and provisional until pinned against a real
 * capture (see docs/adr/0005). A row with no usable PIN or no image bytes yields
 * null rather than a half-formed photo.
 */
final class AttphotoParser
{
    use ParsesAdmsRows;

    public function parse(PushRequest $request): ?AttendancePhoto
    {
        [$header, $image] = $this->splitHeader($request->body);

        if ($image === '') {
            return null;
        }

        $userId = trim($request->param('PIN') ?? $request->param('pin') ?? $header['pin'] ?? '');

        if ($userId === '') {
            return null;
        }

        return new AttendancePhoto(
            userId: $userId,
            capturedAt: $this->parseTimestamp($request->param('time') ?? $header['time'] ?? ''),
            image: $image,
        );
    }

    /**
     * Split an optional leading `Key=Value` metadata line from the image bytes. A
     * body that opens with the JPEG marker, or whose first line carries no `=`, is
     * taken to be all image.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function splitHeader(string $body): array
    {
        if ($body === '' || str_starts_with($body, "\xFF\xD8")) {
            return [[], $body];
        }

        $newline = strpos($body, "\n");

        if ($newline === false) {
            return [[], $body];
        }

        $first = substr($body, 0, $newline);

        if (! str_contains($first, '=')) {
            return [[], $body];
        }

        $header = [];

        foreach (explode("\t", trim($first)) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $header[strtolower(trim($key))] = trim($value);
        }

        return [$header, substr($body, $newline + 1)];
    }
}
