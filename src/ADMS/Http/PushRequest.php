<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Http;

/**
 * A framework-neutral view of an inbound ADMS HTTP request.
 *
 * The core never sees a PSR-7 message or an Illuminate request: a bridge maps
 * whatever it has onto these primitives (method, path, query, raw body) so the
 * handlers stay free of any HTTP framework (see docs/adr/0008).
 */
final readonly class PushRequest
{
    /**
     * @param  string  $method  the HTTP method, upper-cased (e.g. `GET`, `POST`)
     * @param  string  $path  the request path (the configured prefix is allowed; only the final segment is matched on)
     * @param  array<string, string>  $query  decoded query parameters
     * @param  string  $body  the raw request body
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public string $body = '',
    ) {}

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * The device serial number ADMS sends as the `SN` query parameter — the
     * handle every ADMS request is keyed by.
     */
    public function serialNumber(): ?string
    {
        return $this->query['SN'] ?? $this->query['sn'] ?? null;
    }

    /**
     * The final path segment, lower-cased — the ADMS endpoint name
     * (`cdata`, `getrequest`, `devicecmd`). Matching on this keeps the router
     * indifferent to the route prefix the bridge mounts under.
     */
    public function endpoint(): string
    {
        $path = rtrim($this->path, '/');
        $slash = strrpos($path, '/');

        return strtolower($slash === false ? $path : substr($path, $slash + 1));
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
