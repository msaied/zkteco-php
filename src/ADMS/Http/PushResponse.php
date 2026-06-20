<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Http;

/**
 * A framework-neutral reply to an ADMS request: a status code, a plain-text
 * body, and headers. A bridge turns this back into whatever response object its
 * framework expects (see docs/adr/0008).
 *
 * ADMS speaks plain text — a bare `OK`, a config block, or `C:<id>:<cmd>`
 * command lines — so the default content type is `text/plain`.
 */
final readonly class PushResponse
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = ['Content-Type' => 'text/plain; charset=utf-8'],
    ) {}

    public static function ok(string $body = 'OK'): self
    {
        return new self(200, $body);
    }

    public static function badRequest(string $body = 'Bad Request'): self
    {
        return new self(400, $body);
    }

    public static function unauthorized(string $body = 'Unauthorized'): self
    {
        return new self(401, $body);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return new self(404, $body);
    }

    /**
     * Tell the device the upload was not accepted so it holds the batch and
     * retries (governed by the handshake's `ErrorDelay`). Used to hold a pending
     * device's data until it is approved, without losing it.
     */
    public static function unavailable(string $body = 'Service Unavailable'): self
    {
        return new self(503, $body);
    }
}
