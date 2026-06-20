<?php

declare(strict_types=1);

use ZkTeco\TCP\Device;
use ZkTeco\Values\User;

/**
 * A device-local slot for the interactive enrollment probe.
 */
const ENROLL_PROBE_UID = 992;

it('captures a new fingerprint from the sensor', function () {
    if ((getenv('ZKTECO_ENROLL_INTERACTIVE') ?: '') === '') {
        $this->markTestSkipped(
            'Set ZKTECO_ENROLL_INTERACTIVE=1 and stand at the device to run the interactive enrollment test.',
        );
    }

    // Enrollment is interactive: a long timeout gives the person time to press
    // the finger three times, and we must NOT disable the device (that locks the
    // sensor), so this uses connect()/disconnect() rather than the managed
    // session() scope.
    $device = integrationDevice(timeout: 60.0)->connect();
    $probe = new User(uid: ENROLL_PROBE_UID, userId: '99299', name: 'ZK ENROLL');

    try {
        $device->users()->save($probe);

        $done = $device->templates()->enroll($probe, fingerIndex: 0);

        expect($done)->toBeTrue();
        expect($device->templates()->forUser(ENROLL_PROBE_UID))->not->toBeEmpty();
    } finally {
        try {
            $device->users()->delete(ENROLL_PROBE_UID);
        } catch (Throwable) {
            // Nothing to clean up if the create never landed.
        }

        $device->disconnect();
    }
});
