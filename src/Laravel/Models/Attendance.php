<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use ZkTeco\Values\AttendanceRecord;

/**
 * Optional Eloquent persistence for punches pulled or streamed from a device.
 *
 * The core is stateless and returns {@see AttendanceRecord}
 * value objects; this model is the bridge's opt-in way to store them (see
 * docs/adr/0002). Publish and run the package migration to create its table.
 *
 * @property int $uid
 * @property string $user_id
 * @property Carbon $recorded_at
 * @property string $verify_mode
 * @property string $punch_state
 * @property string $connection
 */
class Attendance extends Model
{
    protected $table = 'zkteco_attendance';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
