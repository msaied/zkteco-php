<?php

declare(strict_types=1);

use ZkTeco\Exceptions\ConnectionException;
use ZkTeco\Exceptions\ErrorCode;
use ZkTeco\Exceptions\NetworkException;
use ZkTeco\Exceptions\ResponseException;
use ZkTeco\Exceptions\ZkException;

it('tags a network failure with a stable code and context', function () {
    $exception = NetworkException::connectionFailed('10.0.0.5', 4370, 'timed out');

    expect($exception)->toBeInstanceOf(ZkException::class)
        ->and($exception->errorCode)->toBe(ErrorCode::ConnectionFailed)
        ->and($exception->context)->toBe(['host' => '10.0.0.5', 'port' => 4370, 'reason' => 'timed out'])
        ->and($exception->getMessage())->toContain('10.0.0.5');
});

it('carries the device reply code on an unexpected response', function () {
    $exception = ConnectionException::unexpectedResponse(2001);

    expect($exception->errorCode)->toBe(ErrorCode::UnexpectedResponse)
        ->and($exception->context)->toBe(['command' => 2001]);
});

it('maps the comm-key rejection to the auth-failed code', function () {
    expect(ConnectionException::authFailed()->errorCode)->toBe(ErrorCode::AuthFailed);
});

it('shares the not-connected code across layers', function () {
    expect(ConnectionException::sessionNotOpen()->errorCode)->toBe(ErrorCode::NotConnected)
        ->and(ConnectionException::deviceNotConnected()->errorCode)->toBe(ErrorCode::NotConnected)
        ->and(NetworkException::notConnected()->errorCode)->toBe(ErrorCode::NotConnected);
});

it('flags malformed responses with the invalid-response code', function () {
    expect(ResponseException::tooShort()->errorCode)->toBe(ErrorCode::InvalidResponse)
        ->and(ResponseException::invalidFraming()->errorCode)->toBe(ErrorCode::InvalidResponse);
});
