<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\Values\AttendancePhoto;

/**
 * Dispatched for each photo a device captures at a punch and uploads via
 * `ATTPHOTO`. Listen for this to persist the image or attach it to the matching
 * Attendance record.
 *
 * The raw bytes ride on `$photo->image`; the package does not write them
 * anywhere. `$connection` is the device serial number, since that is how an ADMS
 * device is addressed.
 */
final class AttendancePhotoReceived
{
    use Dispatchable;

    public function __construct(
        public readonly AttendancePhoto $photo,
        public readonly string $connection,
    ) {}
}
