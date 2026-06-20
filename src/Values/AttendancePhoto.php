<?php

declare(strict_types=1);

namespace ZkTeco\Values;

use DateTimeImmutable;

/**
 * A photo a device captured at a punch, uploaded out of band from the
 * Attendance record it belongs to.
 *
 * `userId` is the human-facing employee number (not the device-local uid), and
 * `capturedAt` ties the image back to its punch — though a device does not always
 * send a usable timestamp, so it may be null. `image` is the raw, opaque image
 * payload exactly as the device sent it; its encoding is firmware-specific and is
 * not interpreted by this package (see docs/adr/0005).
 */
final readonly class AttendancePhoto
{
    public function __construct(
        public string $userId,
        public ?DateTimeImmutable $capturedAt,
        public string $image,
        public string $contentType = 'image/jpeg',
    ) {}
}
