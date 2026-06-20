<?php

declare(strict_types=1);

use ZkTeco\TCP\Device;
use ZkTeco\Values\User;

/**
 * A device-local slot high enough to avoid colliding with real enrolments.
 */
const PROBE_UID = 990;

it('sets the device clock and reads it back', function () {
    integrationDevice()->session(function (Device $d): void {
        // Re-set the clock to its own current value: proves the write path
        // round-trips without meaningfully drifting the device time.
        $before = $d->info()->time();
        $d->info()->setTime($before);
        $after = $d->info()->time();

        expect(abs($after->getTimestamp() - $before->getTimestamp()))->toBeLessThan(5);
    });
});

it('creates, reads back, and deletes a user', function () {
    integrationDevice()->session(function (Device $d): void {
        $users = $d->users();

        try {
            $users->save(new User(uid: PROBE_UID, userId: '99099', name: 'ZK INTEG'));

            $found = $users->find(PROBE_UID);

            expect($found)->not->toBeNull();
            expect($found->userId)->toBe('99099');
            expect($found->name)->toBe('ZK INTEG');
        } finally {
            // Always remove the probe, even if an assertion above failed.
            try {
                $users->delete(PROBE_UID);
            } catch (Throwable) {
                // Nothing to clean up if the create never landed.
            }
        }

        expect($users->find(PROBE_UID))->toBeNull();
    });
});
