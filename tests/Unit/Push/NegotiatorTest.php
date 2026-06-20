<?php

declare(strict_types=1);

use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Registry\Negotiator;
use ZkTeco\ADMS\Registry\ProtocolGeneration;

/**
 * @param  array<string, string>  $query
 */
function admsHandshake(array $query): PushRequest
{
    return new PushRequest('GET', 'iclock/cdata', $query);
}

it('treats a handshake without pushver as the legacy generation', function () {
    $device = (new Negotiator)->negotiate(admsHandshake(['SN' => 'ABC123', 'options' => 'all']));

    expect($device->serialNumber)->toBe('ABC123')
        ->and($device->generation)->toBe(ProtocolGeneration::Legacy);
});

it('negotiates the push generation from the pushver parameter', function () {
    expect((new Negotiator)->negotiate(admsHandshake(['SN' => 'A', 'pushver' => '2.4.1']))->generation)
        ->toBe(ProtocolGeneration::PushV2)
        ->and((new Negotiator)->negotiate(admsHandshake(['SN' => 'A', 'pushver' => '3.0.1']))->generation)
        ->toBe(ProtocolGeneration::PushV3);
});

it('reads advertised capabilities from the handshake', function () {
    $device = (new Negotiator)->negotiate(admsHandshake([
        'SN' => 'A',
        'FingerFunOn' => '1',
        'FaceFunOn' => '0',
    ]));

    expect($device->capabilities->fingerprint)->toBeTrue()
        ->and($device->capabilities->face)->toBeFalse();
});
