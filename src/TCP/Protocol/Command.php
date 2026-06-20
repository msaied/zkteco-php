<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

/**
 * ZK-protocol command and acknowledgement codes.
 *
 * Backing values match pyzk's CMD_* / CMD_ACK_* constants. This is the core set
 * needed for the connection lifecycle and bulk data reads; the remaining codes
 * are added as each capability is implemented.
 *
 * @todo Complete the command table during protocol implementation.
 */
enum Command: int
{
    // Configuration / metadata
    case OptionsRead = 11;
    case GetTime = 201;
    case SetTime = 202;

    // Data reads
    case DbRead = 7;
    case UserTempRead = 9;
    case AttlogRead = 13;
    case GetFreeSizes = 50;

    // Data writes
    case UserWrite = 8;
    case ClearData = 14;
    case ClearAttlog = 15;
    case DeleteUser = 18;
    case DeleteUserTemp = 19;
    case SaveUserTemps = 110;

    // Biometric capture / enrollment
    case StartVerify = 60;
    case StartEnroll = 61;
    case CancelCapture = 62;

    // Session lifecycle
    case Connect = 1000;
    case Exit = 1001;
    case EnableDevice = 1002;
    case DisableDevice = 1003;
    case Restart = 1004;
    case PowerOff = 1005;
    case Sleep = 1006;
    case Resume = 1007;
    case RefreshData = 1013;

    // Device info / auth
    case Version = 1100;
    case Auth = 1102;

    // Bulk data transfer
    case PrepareData = 1500;
    case Data = 1501;
    case FreeData = 1502;
    case PrepareBuffer = 1503;
    case ReadBuffer = 1504;

    // Realtime event registration
    case RegEvent = 500;

    // Acknowledgements
    case AckOk = 2000;
    case AckError = 2001;
    case AckData = 2002;
    case AckRetry = 2003;
    case AckUnauthorized = 2005;
}
