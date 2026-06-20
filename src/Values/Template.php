<?php

declare(strict_types=1);

namespace ZkTeco\Values;

/**
 * A biometric enrollment belonging to a User. A User may have several.
 *
 * `data` is the raw, opaque template payload as returned by the device; its
 * format is firmware-specific and is not interpreted by this package.
 *
 * `fingerIndex` is the device's finger slot (0-9), confirmed against the
 * hardware's Enroll screen. The order runs from the left pinky across to the
 * right pinky, with the thumbs meeting in the middle:
 *   0 left pinky   1 left ring    2 left middle  3 left index   4 left thumb
 *   5 right thumb  6 right index  7 right middle 8 right ring    9 right pinky
 */
final readonly class Template
{
    public function __construct(
        public int $uid,
        public int $fingerIndex,
        public bool $valid,
        public string $data,
    ) {}
}
