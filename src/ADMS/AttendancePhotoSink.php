<?php

declare(strict_types=1);

namespace ZkTeco\ADMS;

use ZkTeco\Values\AttendancePhoto;

/**
 * Where a captured punch photo goes once an ADMS handler has decoded an
 * `ATTPHOTO` upload.
 *
 * The same framework-neutral seam as {@see AttendanceSink} (see docs/adr/0008):
 * the handler hands over an {@see AttendancePhoto} and the bridge decides whether
 * to store the bytes, dispatch an event, or push them onward — the core never
 * touches a filesystem or a framework.
 */
interface AttendancePhotoSink
{
    public function receive(AttendancePhoto $photo, string $serialNumber): void;
}
