<?php

declare(strict_types=1);

namespace ZkTeco\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base type for every exception thrown by this package. Catch this to handle
 * any ZKTeco failure regardless of cause.
 *
 * Each instance carries a stable {@see ErrorCode} and a context array of the
 * values that shaped the message (host, port, the device reply code, …). The
 * `getMessage()` text is always English — the format developers and log/error
 * tooling expect — while the code + context let the consuming application
 * localise a user-facing message. The Laravel bridge maps the code to
 * `zkteco::errors.<code>` automatically.
 */
class ZkException extends RuntimeException
{
    /**
     * @param  array<string, scalar|null>  $context  values referenced by the localised message
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        string $message,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
