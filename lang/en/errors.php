<?php

declare(strict_types=1);

/*
 * Default (English) messages for ZkTeco\Exceptions\ErrorCode. Keys match the
 * enum's backing values. Publish with `php artisan vendor:publish --tag=zkteco-lang`
 * and add sibling locale folders (e.g. lang/vendor/zkteco/fr/errors.php) to translate.
 */
return [
    'connection_failed' => 'Unable to connect to the device at :host::port (:reason).',
    'auth_failed' => 'Authentication failed: the comm key is missing or incorrect.',
    'not_connected' => 'Not connected to the device. Open a connection first.',
    'timeout' => 'Timed out waiting for a response from the device.',
    'write_failed' => 'Failed to send the request to the device.',
    'connection_closed' => 'The device closed the connection before sending a full response.',
    'invalid_response' => 'The device returned a malformed or unexpected response.',
    'unexpected_response' => 'The device returned an unexpected response code [:command].',
    'udp_unsupported' => 'UDP transport is not supported yet; use TCP.',
    'unknown_device' => 'No registered device for serial [:serial]; a device must register before it can be commanded.',
    'unsupported_command' => 'The :generation generation cannot render command [:command].',
];
