<?php

declare(strict_types=1);

use ZkTeco\Enums\PunchState;

it('maps the standard punch-state bytes', function () {
    expect(PunchState::from(0))->toBe(PunchState::CheckIn)
        ->and(PunchState::from(1))->toBe(PunchState::CheckOut)
        ->and(PunchState::from(255))->toBe(PunchState::Undefined);
});
