<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\AttendancePhotoSink;
use ZkTeco\Values\AttendancePhoto;

/**
 * An {@see AttendancePhotoSink} that collects what it is handed, so a test can
 * assert which photos a handler emitted and for which serial number.
 */
final class RecordingAttendancePhotoSink implements AttendancePhotoSink
{
    /** @var list<array{photo: AttendancePhoto, serial: string}> */
    public array $received = [];

    public function receive(AttendancePhoto $photo, string $serialNumber): void
    {
        $this->received[] = ['photo' => $photo, 'serial' => $serialNumber];
    }
}
