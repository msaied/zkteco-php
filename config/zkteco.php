<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | The name of the device connection used when none is given explicitly,
    | e.g. ZkTeco::connection() or the zkteco:listen command without an argument.
    |
    */

    'default' => env('ZKTECO_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Each entry describes one physical device. "comm_key" is the numeric
    | communication password guarding the socket session (0 when unset on the
    | device); it is distinct from any user's own password.
    |
    | "name_encoding" is the codepage the device stores user names in. The device
    | reads the raw name bytes using its own configured language, so non-ASCII
    | names must be encoded to match or they show up as garbage on the panel.
    | Leave it "UTF-8" for ASCII-only or Unicode firmware; set it to the device's
    | codepage otherwise — "Windows-1256" for Arabic, "GB2312" for Chinese, etc.
    |
    */

    'connections' => [
        'default' => [
            'host' => env('ZKTECO_HOST', '192.168.1.201'),
            'port' => (int) env('ZKTECO_PORT', 4370),
            'comm_key' => (int) env('ZKTECO_COMM_KEY', 0),
            'timeout' => (float) env('ZKTECO_TIMEOUT', 5),
            'udp' => (bool) env('ZKTECO_UDP', false),
            'name_encoding' => env('ZKTECO_NAME_ENCODING', 'UTF-8'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ADMS push endpoints
    |--------------------------------------------------------------------------
    |
    | ADMS is ZKTeco's device-initiated HTTP push protocol: the device POSTs
    | attendance to us and polls for commands, the inverse of the socket client
    | above. The routes stay dormant until enabled, keeping apps that only use
    | the socket client untouched.
    |
    | Device admission is trust-but-gate, in one of two postures:
    |
    | - Strict (auto_register = false, the default): only serials in
    |   "allowed_serials" are admitted, and they are approved on sight.
    |   Everything else is rejected and never recorded.
    | - Open (auto_register = true): any device may dial in and is recorded, but
    |   an unknown one lands as "pending" — visible, yet its attendance is held
    |   until you approve it (php artisan zkteco:approve <serial>). This is
    |   "accept all, but choose which to add". Serials in "allowed_serials" are
    |   still approved on sight.
    |
    | Recording a device is never the same as trusting its data. Deploy the
    | endpoints behind HTTPS; TLS is not terminated in-package.
    |
    */

    'adms' => [
        'enabled' => (bool) env('ZKTECO_ADMS_ENABLED', false),
        'prefix' => env('ZKTECO_ADMS_PREFIX', 'iclock'),
        'middleware' => [],
        'auto_register' => (bool) env('ZKTECO_ADMS_AUTO_REGISTER', false),
        'allowed_serials' => array_values(array_filter(
            explode(',', (string) env('ZKTECO_ADMS_ALLOWED_SERIALS', '')),
        )),
    ],
];
