<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use ZkTeco\Exceptions\ConnectionException;
use ZkTeco\Exceptions\NetworkException;
use ZkTeco\Laravel\ZkExceptionRenderer;

it('registers the packaged error translations under the zkteco namespace', function () {
    expect(trans('zkteco::errors.auth_failed'))
        ->toBe('Authentication failed: the comm key is missing or incorrect.');
});

it('interpolates exception context into the translated message', function () {
    expect(trans('zkteco::errors.unexpected_response', ['command' => 2001]))
        ->toContain('2001');
});

it('falls back to another locale when one is published', function () {
    app('translator')->addLines(['errors.auth_failed' => 'Échec de l’authentification.'], 'fr', 'zkteco');
    app()->setLocale('fr');

    $message = (new ZkExceptionRenderer)->message(ConnectionException::authFailed());

    expect($message)->toBe('Échec de l’authentification.');
});

it('renders a localised JSON error for API requests', function () {
    $request = Request::create('/devices', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = (new ZkExceptionRenderer)->render(ConnectionException::authFailed(), $request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toBe([
            'message' => 'Authentication failed: the comm key is missing or incorrect.',
            'error_code' => 'auth_failed',
        ]);
});

it('defers non-JSON requests to the default handler', function () {
    $request = Request::create('/devices', 'GET');

    expect((new ZkExceptionRenderer)->render(NetworkException::timeout(), $request))->toBeNull();
});
