<?php

declare(strict_types=1);

use ZkTeco\TCP\Device;
use ZkTeco\Values\Template;
use ZkTeco\Values\User;

/**
 * A device-local slot high enough to avoid colliding with real enrolments, and
 * distinct from the user-write probe.
 */
const TEMPLATE_PROBE_UID = 991;

it('uploads a cloned template to a probe user and reads it back', function () {
    $device = integrationDevice();

    // Clone an existing template's bytes: a valid blob the device will accept.
    $existing = $device->session(fn (Device $d) => $d->templates()->all());

    if ($existing === []) {
        $this->markTestSkipped('Device has no enrolled template to clone for the upload round-trip.');
    }

    $blob = $existing[0]->data;
    $probe = new User(uid: TEMPLATE_PROBE_UID, userId: '99199', name: 'ZK TMPL');

    $device->session(function (Device $d) use ($probe, $blob): void {
        try {
            $d->users()->save($probe);
            $d->templates()->upload($probe, [
                new Template(uid: TEMPLATE_PROBE_UID, fingerIndex: 0, valid: true, data: $blob),
            ]);

            $mine = $d->templates()->forUser(TEMPLATE_PROBE_UID);

            expect($mine)->not->toBeEmpty();
            expect($mine[0]->data)->toBe($blob);
        } finally {
            // Deleting the user clears its templates too.
            try {
                $d->users()->delete(TEMPLATE_PROBE_UID);
            } catch (Throwable) {
                // Nothing to clean up if the create never landed.
            }
        }

        expect($d->templates()->forUser(TEMPLATE_PROBE_UID))->toBe([]);
    });
});
