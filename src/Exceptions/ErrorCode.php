<?php

declare(strict_types=1);

namespace ZkTeco\Exceptions;

/**
 * Stable, locale-independent identifiers for every failure the package raises.
 *
 * The backing value doubles as the translation key: the Laravel bridge resolves
 * messages through `zkteco::errors.<value>`, and non-Laravel consumers can map
 * the same value to their own catalogue. These values are part of the public
 * contract — do not rename them.
 */
enum ErrorCode: string
{
    case ConnectionFailed = 'connection_failed';
    case AuthFailed = 'auth_failed';
    case NotConnected = 'not_connected';
    case Timeout = 'timeout';
    case WriteFailed = 'write_failed';
    case ConnectionClosed = 'connection_closed';
    case InvalidResponse = 'invalid_response';
    case UnexpectedResponse = 'unexpected_response';
    case UdpUnsupported = 'udp_unsupported';
    case UnknownDevice = 'unknown_device';
    case UnsupportedCommand = 'unsupported_command';
}
