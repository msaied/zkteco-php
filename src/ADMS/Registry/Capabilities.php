<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

/**
 * What a Registered device advertises it can do — the biometric and photo
 * features reported at registration.
 *
 * Legacy handshakes rarely advertise these, so every flag defaults to `false`
 * and the raw advertised options are kept alongside them. The flags gain
 * meaning once a real handshake is captured (see docs/adr/0005); until then they
 * are carried, not relied upon.
 */
final readonly class Capabilities
{
    /**
     * @param  array<string, string>  $raw  the advertised options, verbatim
     */
    public function __construct(
        public bool $fingerprint = false,
        public bool $face = false,
        public bool $userPhoto = false,
        public array $raw = [],
    ) {}
}
