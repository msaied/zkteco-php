<?php

declare(strict_types=1);

/*
 * Approve, block, or list ADMS devices for the listener demo.
 *
 *   php examples/adms-approve.php                  # list devices + status
 *   php examples/adms-approve.php <SERIAL>         # approve -> its attendance flows
 *   php examples/adms-approve.php <SERIAL> --block # refuse the device
 *
 * Shares examples/adms-demo.sqlite with adms-listener.php, so run it in a second
 * terminal while the listener is up.
 */

require_once __DIR__.'/DemoRegistry.php';

const DB = __DIR__.'/adms-demo.sqlite';

$registry = new DemoRegistry(DB, autoRegister: true);
$serial = $argv[1] ?? null;

if ($serial === null) {
    $devices = $registry->all();

    if ($devices === []) {
        echo "No devices have dialed in yet. Start the listener and point a device at it.\n";
        exit(0);
    }

    printf("%-20s %-10s %-10s %s\n", 'SERIAL', 'STATUS', 'GEN', 'LAST SEEN');
    foreach ($devices as $device) {
        printf("%-20s %-10s %-10s %s\n", $device['serial'], $device['status'], $device['generation'], $device['last_seen']);
    }

    exit(0);
}

if ($registry->find($serial) === null) {
    fwrite(STDERR, "No device [{$serial}] has dialed in yet.\n");
    exit(1);
}

if (in_array('--block', $argv, true)) {
    $registry->block($serial);
    echo "Blocked [{$serial}]. It will be rejected on its next request.\n";
    exit(0);
}

$registry->approve($serial);
echo "Approved [{$serial}]. Its next upload will be ingested — watch the listener.\n";
