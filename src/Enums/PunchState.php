<?php

declare(strict_types=1);

namespace ZkTeco\Enums;

/**
 * What a punch means on an Attendance record.
 *
 * Corresponds to pyzk's `punch` field. Many devices report 0xFF (255) when the
 * punch state is not configured, modelled here as {@see PunchState::Undefined}.
 *
 * @todo Confirm the 0..5 mapping against target hardware (see docs/adr/0005).
 */
enum PunchState: int
{
    case CheckIn = 0;
    case CheckOut = 1;
    case BreakOut = 2;
    case BreakIn = 3;
    case OvertimeIn = 4;
    case OvertimeOut = 5;
    case Undefined = 255;
}
