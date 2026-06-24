# zkteco

[![Packagist Version](https://img.shields.io/packagist/v/msaied/zkteco)](https://packagist.org/packages/msaied/zkteco)
[![Packagist Downloads](https://img.shields.io/packagist/dt/msaied/zkteco)](https://packagist.org/packages/msaied/zkteco)
[![PHP Version](https://img.shields.io/packagist/dependency-v/msaied/zkteco/php)](https://packagist.org/packages/msaied/zkteco)
[![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/msaied/zkteco-php/ci.yml)](https://github.com/msaied/zkteco-php/actions/workflows/ci.yml)
[![GitHub License](https://img.shields.io/github/license/msaied/zkteco-php)](https://github.com/msaied/zkteco-php/blob/main/LICENSE)

A PHP client for **ZKTeco** biometric attendance devices, with an optional,
auto-discovered Laravel bridge. It speaks both directions of the wire:

- the **TCP socket protocol** (a port of [pyzk](https://github.com/fananimi/pyzk)) —
  *you* dial the device on port 4370 to read users, attendance, and templates,
  stream live punches, and run interactive fingerprint enrollment; and
- the **ADMS push protocol** — the *device* dials *you* over HTTP, uploading
  attendance, photos, biometric data, and audit logs while polling for typed
  commands you queue back to it.

The socket protocol is **verified end-to-end against real hardware** (read,
write, template upload, realtime streaming, and interactive enrollment all work
— see [Tested hardware](#tested-hardware)). The ADMS read path is fully
implemented behind a trust-but-gate admission model; some outbound ADMS command
layouts are still provisional (see [Limitations](#limitations)).

## Two ways to talk to a device

| | **Socket client** (`ZkTeco\TCP`) | **ADMS push** (`ZkTeco\ADMS`) |
| --- | --- | --- |
| Who initiates | Your app dials the device | The device dials your app |
| Transport | TCP, port 4370 | HTTP(S), device → your endpoint |
| Good for | On-demand reads/writes, live streaming, enrollment | Always-on fleets, NAT'd devices, push-on-punch |
| Entry point | `new Device(...)` / `ZkTeco::connection()` | Mounted routes + events / `ZkTeco::push($serial)` |
| Needs a daemon? | Only for `realtime()->live()` | No — devices push on their own schedule |

You can use either or both. The two paths share the same domain
([value objects & enums](#value-objects--enums)): a punch arriving over the
socket stream and one pushed over ADMS both surface as the same
`AttendanceRecord` and the same `PunchReceived` event.

## Features

- **Agnostic core** in `src/TCP` and `src/ADMS` with no `illuminate/*`
  dependency — the binary socket protocol and the ADMS HTTP protocol are both
  framework-neutral.
- **Grouped sub-service API** reached from a single `Device` entry point rather
  than one god class: `$device->users()`, `->attendance()`, `->templates()`,
  `->control()`, `->info()`, `->realtime()`.
- **Managed session scope** that disables the device for the duration of the
  work and guarantees it is re-enabled and disconnected even when the body
  throws.
- **Immutable value objects** (`User`, `AttendanceRecord`, `Template`,
  `OperationLog`, `AttendancePhoto`, `BiometricTemplate`) and typed **enums**
  (`Privilege`, `PunchState`, `VerifyMode`, `OperationType`) — no loose arrays.
- **Realtime punch streaming** via a PHP `Generator`.
- **Interactive fingerprint enrollment** driven from your code.
- **ADMS push endpoints** that ingest attendance, attendance photos, biometric
  templates, user syncs, and audit logs — with a **trust-but-gate** device
  admission model (strict allowlist or accept-then-approve).
- **Typed outbound ADMS commands** — queue `reboot`, `syncTime`, `upsertUser`,
  `pushTemplate`, etc. for a device to run on its next poll; outcomes arrive as
  events.
- **Optional Laravel bridge**: facade, config, three artisan commands, a set of
  events, and Eloquent models — auto-discovered when installed inside a Laravel
  app, dormant otherwise.

## Requirements

- PHP **8.3+**
- The Laravel bridge requires **Laravel 11, 12, or 13** (provided by the host
  app; not a hard dependency of the package).

## Installation

```bash
composer require msaied/zkteco
```

The Laravel bridge is auto-discovered. To customise connections, publish the
config:

```bash
php artisan vendor:publish --tag=zkteco-config
```

---

# Part 1 — Socket client (you dial the device)

## Quick start

```php
use ZkTeco\TCP\Device;

$device = new Device(host: '192.168.1.201');

// Managed scope: connects, disables the device for the duration, then
// re-enables and disconnects it even if the callback throws.
$users = $device->session(fn (Device $d) => $d->users()->all());

foreach ($users as $user) {
    echo "{$user->uid}\t{$user->userId}\t{$user->name}\n";
}
```

### Connecting

The `Device` constructor only describes the connection — no socket is opened
until you connect.

```php
$device = new Device(
    host: '192.168.1.201',
    port: 4370,    // default ZK port
    commKey: 0,    // numeric comm password guarding the session (0 if unset)
    timeout: 5.0,  // socket timeout in seconds
    useUdp: false, // UDP is not implemented yet — see Limitations
    nameEncoding: 'UTF-8', // device name codepage — see "Non-ASCII names" below
);
```

#### Non-ASCII names (Arabic, Chinese, …)

The device stores user names in a fixed byte field and renders them using its
own configured language codepage, **not** UTF-8. Writing UTF-8 names to a device
set to another codepage shows mojibake on the panel even though the device can
display the script. Set `nameEncoding` to the device's codepage so names are
converted on write and back on read:

```php
$device = new Device(host: '192.168.1.201', nameEncoding: 'Windows-1256'); // Arabic
```

Common values: `Windows-1256` (Arabic), `GB2312` (Chinese), `Windows-1251`
(Cyrillic). Leave it `UTF-8` for ASCII-only or Unicode firmware. Encoding is
done with `iconv`, so any encoding it supports is valid.

There are two ways to scope a connection:

**Managed scope (preferred)** — `session()` connects, disables the device while
the callback runs, then re-enables and disconnects in a `finally`, even on
exceptions. Use this for ordinary read/write work:

```php
$device->session(function (Device $d) {
    $d->users()->save(new User(uid: 5, userId: '1005', name: 'Asma'));

    return $d->attendance()->all();
});
```

**Explicit lifecycle** — `connect()` / `disconnect()` for long-lived work such
as realtime listening or interactive enrollment, where you do *not* want the
device disabled:

```php
$device->connect();
try {
    // ... long-lived work ...
} finally {
    $device->disconnect();
}
```

> **Note:** `session()` disables the device, which also locks the fingerprint
> sensor. Use `connect()`/`disconnect()` for `realtime()->live()` and
> `templates()->enroll()`.

## Working with the device

### Users

```php
use ZkTeco\Values\User;
use ZkTeco\Enums\Privilege;

$users = $device->users();

$users->all();                 // list<User>
$users->find(5);               // ?User by device-local uid
$users->save(new User(         // create or overwrite
    uid: 5,
    userId: '1005',            // human-facing employee number
    name: 'Asma',
    privilege: Privilege::User,
    password: null,
    cardNumber: null,
    groupId: 0,
));
$users->delete(5);             // delete by uid (also clears that user's templates)
$users->clear();               // wipe all users, fingerprints and attendance
```

> `uid` is the device-local record slot (1..N); `userId` is the human-facing
> employee number string. They are distinct and must never be conflated.

### Attendance

```php
$attendance = $device->attendance();

$records = $attendance->all(); // list<AttendanceRecord>
$attendance->clear();          // wipe the on-device attendance log
```

### Templates & fingerprint enrollment

A `Template` is one biometric enrollment belonging to a user; a user may have
several. `data` is the raw, opaque, firmware-specific template payload — this
package does not interpret it.

```php
$templates = $device->templates();

$templates->all();                   // list<Template> — every template on the device
$templates->forUser(5);              // list<Template> for one user's uid
$templates->delete(5, 0);            // delete user 5's finger slot 0
$templates->upload($user, $fingers); // store a list<Template> for a user
```

**Interactive enrollment** triggers the device's fingerprint sensor and blocks
while the person presses their finger (typically 3×). It returns `true` on a
successful capture. Run it on an explicit connection, **not** inside
`session()` (which would disable the sensor):

```php
$device->connect();
try {
    $captured = $device->templates()->enroll($user, fingerIndex: 6); // bool
} finally {
    $device->disconnect();
}
```

Because the enroll event stream is **firmware-specific** (see
[Tested hardware](#tested-hardware)), `enroll()` accepts an optional `$trace`
closure — `fn (string $event, array $context)` — invoked at each step of the
handshake: the `CMD_STARTENROLL` payload and reply, every event packet (with its
raw hex), the parsed completion, and the trailing records drained afterwards. On
a terminal whose firmware differs from the verified one, pass it to record
exactly what the device returns instead of guessing:

```php
$device->templates()->enroll($user, fingerIndex: 6, trace: function (string $event, array $context) {
    logger()->debug("enroll: {$event}", $context);
});
```

The `fingerIndex` (`0`–`9`) is the device's finger slot, used by `enroll()`,
`delete()` and `Template`. It runs from the left pinky across to the right
pinky, with the thumbs meeting in the middle — matching the device's on-screen
Enroll layout:

| Index | Finger      | Index | Finger       |
| ----- | ----------- | ----- | ------------ |
| `0`   | left pinky  | `5`   | right thumb  |
| `1`   | left ring   | `6`   | right index  |
| `2`   | left middle | `7`   | right middle |
| `3`   | left index  | `8`   | right ring   |
| `4`   | left thumb  | `9`   | right pinky  |

> **Face enrollment is not supported over the socket protocol** — see
> [Limitations](#limitations).

### Device info

```php
$info = $device->info();

$info->firmwareVersion(); // e.g. "Ver 6.60 May 14 2018"
$info->serialNumber();    // device serial
$info->name();            // device name / model ('' if unset)
$info->time();            // DateTimeImmutable — the device clock
$info->setTime(new DateTimeImmutable('now'));
```

### Device control

```php
$control = $device->control();

$control->disable();   // put the device into maintenance mode
$control->enable();    // bring it back
$control->restart();
$control->powerOff();
$control->clearData(); // factory-style data wipe (users + templates + attendance)
```

### Realtime punches

`live()` registers for live attendance events and returns a `Generator` that
yields an `AttendanceRecord` per punch, or `null` on an idle heartbeat (so the
loop never blocks forever). Run it on an explicit connection:

```php
$device->connect();
foreach ($device->realtime()->live() as $record) {
    if ($record === null) {
        continue; // idle heartbeat — no punch this interval
    }

    echo "{$record->userId} punched at {$record->recordedAt->format('H:i:s')}\n";
}
```

---

# Part 2 — ADMS push (the device dials you)

ADMS is ZKTeco's device-initiated HTTP protocol — the inverse of the socket
client. Instead of you dialing the device, the device is configured with your
server's address and **pushes** to it: it handshakes, uploads attendance (and,
on capable firmware, attendance photos, biometric templates, and audit logs),
and polls for commands you've queued. This is the right fit for always-on
fleets, devices behind NAT, or any case where you want push-on-punch without
holding an open socket.

The package ships the whole HTTP surface as routes plus a controller; you wire
your app in through **events** (for data the device uploads) and the
`ZkTeco::push()` **fluent API** (for commands you send back).

## Enabling the endpoints

The ADMS routes stay **dormant by default** so an app that only uses the socket
client never exposes a push surface. Turn them on in `config/zkteco.php` (or via
env):

```php
'adms' => [
    'enabled'         => (bool) env('ZKTECO_ADMS_ENABLED', false),
    'prefix'          => env('ZKTECO_ADMS_PREFIX', 'iclock'),
    'middleware'      => [],   // e.g. ['throttle:adms']
    'auto_register'   => (bool) env('ZKTECO_ADMS_AUTO_REGISTER', false),
    'allowed_serials' => array_values(array_filter(
        explode(',', (string) env('ZKTECO_ADMS_ALLOWED_SERIALS', '')),
    )),
],
```

```dotenv
ZKTECO_ADMS_ENABLED=true
ZKTECO_ADMS_PREFIX=iclock
ZKTECO_ADMS_AUTO_REGISTER=false
ZKTECO_ADMS_ALLOWED_SERIALS=ABC1234567890,DEF0987654321
```

With `enabled = true`, the bridge mounts these routes under the prefix (default
`iclock`), which is the path ZKTeco firmware expects:

| Method | Path | Purpose |
| ------ | ---- | ------- |
| GET    | `/iclock/cdata`      | handshake / config negotiation |
| POST   | `/iclock/cdata`      | data upload (attendance, photos, biodata, oplog) |
| GET    | `/iclock/getrequest` | device polls for queued commands |
| POST   | `/iclock/devicecmd`  | device reports command results |
| GET    | `/iclock/registry`   | PUSH-SDK registration |

On the device, point **Comm → Cloud Server / ADMS Setup** at your server's
address and port (disable "Enable Domain Name" if you're using an IP). Deploy
behind **HTTPS** — TLS is not terminated in-package.

You also need the device/command tables (see [Persistence](#persistence-models--migrations)):

```bash
php artisan vendor:publish --tag=zkteco-migrations
php artisan migrate
```

## Device admission: trust but gate

Recording a device is never the same as trusting its data. Admission has two
postures, set by `auto_register`:

- **Strict (`auto_register = false`, the default).** Only serials in
  `allowed_serials` are admitted, and they are approved on sight. Every other
  device is rejected and never recorded.
- **Open (`auto_register = true`).** Any device may dial in and is recorded, but
  an unknown one lands as **pending** — visible, yet its attendance is *held*
  (the device is told to retry) until you approve it. Serials in
  `allowed_serials` are still approved on sight. This is "accept all, but choose
  which to keep".

A device is always in one of three states — `pending`, `approved`, or
`blocked` (`ZkTeco\ADMS\Registry\DeviceStatus`).

### Approving devices

Two artisan commands manage the fleet:

```bash
php artisan zkteco:devices            # list every device + status
php artisan zkteco:devices --pending  # only those awaiting approval

php artisan zkteco:approve <serial>           # approve — its uploads start flowing
php artisan zkteco:approve <serial> --block   # block — rejected on its next request
```

You can also approve programmatically through the registry contract
(`ZkTeco\ADMS\Registry\DeviceRegistry`), resolvable from the container:

```php
use ZkTeco\ADMS\Registry\DeviceRegistry;

app(DeviceRegistry::class)->approve('ABC1234567890');
```

## Reacting to uploaded data

Each kind of upload is parsed into a value object and dispatched as a Laravel
event carrying the originating **serial number** as `$connection`. Listen to the
ones you care about:

| Event | Fired for | Payload |
| ----- | --------- | ------- |
| `PunchReceived`           | every attendance punch (ATTLOG / RTLOG) | `$record: AttendanceRecord`, `$connection: string` |
| `AttendancePhotoReceived` | punch-time photo (ATTPHOTO) | `$photo: AttendancePhoto`, `$connection: string` |
| `BiometricReceived`       | biometric template (BIODATA, PUSH-SDK) | `$template: BiometricTemplate`, `$connection: string` |
| `UserReceived`            | user synced from the device (USERINFO) | `$user: User`, `$connection: string` |
| `OperationLogged`         | audit entry (enroll, delete, settings, power) | `$entry: OperationLog`, `$connection: string` |
| `DeviceRegistered`        | a device registers for the first time | `$device: RegisteredDevice` |
| `CommandAcknowledged`     | a queued command's outcome came back | `$command: QueuedCommand`, `$result: CommandResult` |

`PunchReceived` is the **same event** the socket listener fires (see
[`zkteco:listen`](#streaming-socket-punches-zktecolisten)), so a single
listener can absorb punches from both transports — telling them apart by
`$connection` (a configured connection name from the socket path, a device
serial from the ADMS path) if you need to.

```php
use ZkTeco\Laravel\Events\PunchReceived;
use ZkTeco\Laravel\Models\Attendance;

class StorePunch
{
    public function handle(PunchReceived $event): void
    {
        Attendance::create([
            'connection'  => $event->connection, // serial (ADMS) or connection name (socket)
            'uid'         => $event->record->uid,
            'user_id'     => $event->record->userId,
            'recorded_at' => $event->record->recordedAt,
            'verify_mode' => $event->record->verifyMode->name,
            'punch_state' => $event->record->punchState->name,
        ]);
    }
}
```

Attendance photos and biometric blobs are handed to you as opaque bytes — the
package never persists them for you:

```php
class ArchivePhoto
{
    public function handle(\ZkTeco\Laravel\Events\AttendancePhotoReceived $event): void
    {
        Storage::put(
            "punches/{$event->connection}/{$event->photo->userId}.jpg",
            $event->photo->image, // raw JPEG bytes
        );
    }
}
```

## Sending commands back to a device

ADMS is poll-based, so commands are **asynchronous**: you queue a typed command,
the device drains it on its next `getrequest` poll, and the outcome arrives later
as a `CommandAcknowledged` event. `ZkTeco::push($serial)` returns a fluent
builder over an already-registered device (it throws
`CommandException::unknownDevice` for an unknown serial):

```php
use ZkTeco\Laravel\Facades\ZkTeco;
use ZkTeco\Values\User;

ZkTeco::push('ABC1234567890')->reboot();
ZkTeco::push('ABC1234567890')->syncTime();                 // defaults to now
ZkTeco::push('ABC1234567890')->upsertUser(new User(
    uid: 0, userId: '1005', name: 'Asma',
));
ZkTeco::push('ABC1234567890')->deleteUser('1005');
ZkTeco::push('ABC1234567890')->clearLog();
```

Each call returns a `QueuedCommand` handle (its `id` correlates the later
acknowledgement). The full set:

| Method | What it queues |
| ------ | -------------- |
| `reboot()` / `restart()` | reboot the device |
| `powerOff()`             | power the device off |
| `enable()` / `disable()` | toggle device availability |
| `clearData()`            | wipe users + templates + attendance |
| `clearLog()`             | wipe the attendance log |
| `clearPhoto()`           | wipe stored photos |
| `syncTime(?DateTimeImmutable $at = null)` | set the device clock |
| `queryData(string $table)` | ask the device to re-upload a table |
| `deleteUser(string $pin)`  | delete a user by employee number |
| `upsertUser(User $user)`   | create or update a user |
| `pushTemplate(BiometricTemplate $template)` | push a biometric template |

Then react to outcomes:

```php
use ZkTeco\Laravel\Events\CommandAcknowledged;

class TrackCommand
{
    public function handle(CommandAcknowledged $event): void
    {
        if ($event->result->succeeded()) {        // returnCode === 0
            logger()->info("ok: {$event->command->command}");
        } else {
            logger()->warning("device returned {$event->result->returnCode}");
        }
    }
}
```

> **Wire-format caveat:** the power, `SET OPTIONS`, and data-write command
> layouts are provisional and not yet pinned against real hardware (the
> attendance/registration read path is). See [Limitations](#limitations).

## Persistence (models & migrations)

These migrations are **optional** — the package never runs them for you. The
service provider only *publishes* them; it does not auto-load them, so they
exist only if you deliberately publish and migrate. Publishing
`zkteco-migrations` copies three tables into your app, each with a matching
Eloquent model under `ZkTeco\Laravel\Models`:

| Table | Model | Holds |
| ----- | ----- | ----- |
| `zkteco_devices`    | `Device`     | registered devices: `serial_number`, `protocol_generation`, `status`, `capabilities`, `stamps`, `last_seen_at` |
| `zkteco_commands`   | `Command`    | queued/sent/acked commands: `serial_number`, `command`, `status`, `return_code`, `sent_at`, `acknowledged_at` |
| `zkteco_attendance` | `Attendance` | optional store for punches you choose to persist: `uid`, `user_id`, `recorded_at`, `verify_mode`, `punch_state`, `connection` |

### When do you actually need them?

| How you use the package | Migrations needed |
| --- | --- |
| Pure PHP core (`ZkTeco\TCP` / `ZkTeco\ADMS`, no Laravel) | **None** — the core never touches a database. |
| Laravel + socket client only (`ZkTeco::connection()`) | **None** — the TCP path doesn't persist anything. |
| Laravel + ADMS push endpoints | `zkteco_devices` + `zkteco_commands` (see below) |

`zkteco_devices` and `zkteco_commands` back the ADMS registry and command queue,
so they are required *only once you enable the push endpoints with the built-in
Eloquent persistence*. Bind your own `DeviceRegistry` / `CommandQueue`
implementations (in-memory, Redis, …) and you can skip the tables entirely.

`zkteco_attendance` is **always optional** — it's an opt-in convenience store for
your own listeners, and the package never writes to it itself.

## Using the ADMS core without Laravel

The ADMS core (`ZkTeco\ADMS`) is framework-neutral. You can mount it on any
HTTP stack by feeding requests to `PushRouter` and implementing the sink
interfaces (`AttendanceSink`, `AttendancePhotoSink`, `BiometricSink`,
`UserSink`, `OperationLogSink`) and the `DeviceRegistry` / `CommandQueue`
contracts yourself. A runnable, dependency-free demo lives in
[`examples/`](examples/):

```bash
# Terminal 1 — a tiny PHP built-in-server listener wired to the real core
php -S 0.0.0.0:8080 examples/adms-listener.php

# Terminal 2 — approve a device that has dialed in
php examples/adms-approve.php <serial>
```

---

## Value objects & enums

| Value object        | Fields                                                                                  |
| ------------------- | --------------------------------------------------------------------------------------- |
| `User`              | `uid`, `userId`, `name`, `privilege`, `password`, `cardNumber`, `groupId`               |
| `AttendanceRecord`  | `userId`, `recordedAt`, `verifyMode`, `punchState`, `uid`                               |
| `Template`          | `uid`, `fingerIndex`, `valid`, `data`                                                    |
| `OperationLog`      | `operation`, `code`, `operatorId`, `occurredAt`, `target`, `parameters`                 |
| `AttendancePhoto`   | `userId`, `capturedAt`, `image`, `contentType`                                           |
| `BiometricTemplate` | `userId`, `type`, `index`, `valid`, `data`                                               |

| Enum            | Cases                                                                                                                                                  |
| --------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Privilege`     | `User` (0), `Enroller` (2), `Manager` (6), `Admin` (14)                                                                                              |
| `PunchState`    | `CheckIn`, `CheckOut`, `BreakOut`, `BreakIn`, `OvertimeIn`, `OvertimeOut`, `Undefined`                                                              |
| `VerifyMode`    | `Password`, `Fingerprint`, `Face`, `Card`, `Other`                                                                                                  |
| `OperationType` | `Startup`, `Shutdown`, `VerifyFailed`, `Alarm`, `MenuEntered`, `SettingsChanged`, `FingerprintEnrolled`, `PasswordEnrolled`, `CardEnrolled`, `UserDeleted`, `FingerprintDeleted`, `DataCleared`, `Other` |

- **Verify mode** is *how* identity was confirmed (pyzk's confusingly named
  `status` field).
- **Punch state** is *what the punch means* (pyzk's `punch` field).

> The socket path fills `AttendanceRecord->uid` (the device slot); the ADMS path
> leaves it `null` and keys on `userId`, because the device doesn't send its
> internal slot over push.

## Laravel integration reference

When installed inside a Laravel app the `ZkTecoServiceProvider` is
auto-discovered. Configure socket connections in `config/zkteco.php`:

```php
return [
    'default' => env('ZKTECO_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'host'     => env('ZKTECO_HOST', '192.168.1.201'),
            'port'     => (int) env('ZKTECO_PORT', 4370),
            'comm_key' => (int) env('ZKTECO_COMM_KEY', 0),
            'timeout'  => (float) env('ZKTECO_TIMEOUT', 5),
            'udp'      => (bool) env('ZKTECO_UDP', false),
            'name_encoding' => env('ZKTECO_NAME_ENCODING', 'UTF-8'), // device codepage for names
        ],
    ],

    'adms' => [ /* see "Enabling the endpoints" above */ ],
];
```

Resolve a configured socket `Device` through the facade:

```php
use ZkTeco\Laravel\Facades\ZkTeco;

$users = ZkTeco::connection()->session(            // default connection
    fn ($device) => $device->users()->all()
);

$users = ZkTeco::connection('warehouse')->session( // a named connection
    fn ($device) => $device->users()->all()
);
```

### Streaming socket punches (`zkteco:listen`)

The socket realtime stream is a blocking, infinite loop, so it needs its own
long-running process — you can't run it inside an HTTP request. The
`zkteco:listen` command is that daemon: it holds the connection open and fires a
`PunchReceived` event for every punch.

```bash
php artisan zkteco:listen            # default connection
php artisan zkteco:listen warehouse  # a named connection
```

Run it under a supervisor (Horizon, systemd, or supervisord) so it restarts
after a dropped connection; it traps `SIGINT`/`SIGTERM` for graceful shutdown.

Reach for it only when you need to react the *instant* someone punches and you're
dialing the device (socket path). If the device pushes to you over ADMS, you
already get `PunchReceived` with no daemon. If periodic syncing is enough,
schedule a job that calls `attendance()->all()` instead.

| You want…                                          | Use                                  |
| -------------------------------------------------- | ------------------------------------ |
| Push-on-punch from an always-on device             | ADMS endpoints + `PunchReceived`     |
| Instant reaction while *you* hold the socket       | `zkteco:listen`                      |
| Periodic pull of the stored log                    | A scheduled `attendance()->all()`    |

### Artisan commands

| Command | Purpose |
| ------- | ------- |
| `zkteco:listen {connection?}`          | Stream live socket punches as `PunchReceived` events |
| `zkteco:devices {--pending}`           | List ADMS devices and their approval status |
| `zkteco:approve {serial} {--block}`    | Approve (or `--block`) an ADMS device by serial |

## Error handling

All failures derive from `ZkTeco\Exceptions\ZkException` (each carries a typed
`ErrorCode` and a context array):

- `ConnectionException` — could not connect/authenticate, device not connected,
  or an unsupported transport (e.g. UDP).
- `NetworkException` — socket-level read/write failures and timeouts.
- `ResponseException` — the device received the command but rejected it.
- `CommandException` — an ADMS command targeted an unknown device, or the
  device's protocol generation can't render that command.

```php
use ZkTeco\Exceptions\ConnectionException;
use ZkTeco\Exceptions\ResponseException;

try {
    $device->session(fn ($d) => $d->users()->save($user));
} catch (ConnectionException $e) {
    // unreachable, wrong comm key, etc.
} catch (ResponseException $e) {
    // device refused the write
}
```

### Localizing error messages

Every exception pairs a human-readable English `getMessage()` (for logs and
developers) with two machine-stable fields that drive translation:

- **`$e->errorCode`** — a `ZkTeco\Exceptions\ErrorCode` enum whose backing
  string (`connection_failed`, `timeout`, …) is the translation key. These
  values are part of the public contract and never change.
- **`$e->context`** — an array of the values that shaped the message
  (`host`, `port`, `command`, …), used as the placeholder bindings.

```php
catch (ZkTeco\Exceptions\ZkException $e) {
    $e->errorCode->value; // 'connection_failed'  — switch on this, or use it as a key
    $e->context;          // ['host' => '192.168.1.201', 'port' => 4370, 'reason' => '...']
    $e->getMessage();     // English fallback, always populated
}
```

In a Laravel app the bridge resolves each code through `zkteco::errors.<code>`,
passing `context` as the replacement bindings and falling back to the English
`getMessage()` when no translation exists for the active locale. Any
`ZkException` reaching the handler on a JSON request is rendered as
`{ "message": "<localised>", "error_code": "<code>" }` with HTTP 503.

To translate them:

```bash
php artisan vendor:publish --tag=zkteco-lang
```

This copies the catalogue to `lang/vendor/zkteco/en/errors.php`. Add a sibling
locale folder using the **same keys** (the placeholders are filled from each
exception's `context`):

```php
// lang/vendor/zkteco/fr/errors.php
return [
    'connection_failed' => 'Connexion impossible à l’appareil :host::port (:reason).',
    'timeout' => 'Délai dépassé en attendant une réponse de l’appareil.',
    // … one entry per ErrorCode value
];
```

Set the app locale and the JSON `message` switches accordingly. Outside Laravel,
map `errorCode->value` to your own catalogue — the codes are stable, so no
parsing of message strings is required.

The full list of keys lives in [`lang/en/errors.php`](lang/en/errors.php), one
per `ErrorCode` case.

## Tested hardware

The socket protocol is verified end-to-end against a physical unit:

| Property  | Value                       |
| --------- | --------------------------- |
| Model     | **MB2000/ID**               |
| Firmware  | **Ver 6.60 May 14 2018**    |
| Transport | TCP, port 4370, comm key `0`|

Verified on this device: handshake + metadata read, buffered user and
attendance reads, clock set/read, user create/read/delete, fingerprint
template upload (byte-for-byte round-trip), realtime event registration, and
interactive fingerprint capture via `CMD_STARTENROLL`.

> The enrollment **event stream is firmware-specific** and differs from pyzk's
> published sequence; `enroll()` was written against this firmware. Only the
> success path has been observed so far — failure result codes (e.g. duplicate
> finger) are not yet characterised.

A gated integration suite exercises all of the above against real hardware —
see [Testing](#testing).

### Compatibility notes

The package targets the **generic ZK protocol**, not one firmware build, so
other ZKTeco terminals are expected to interoperate. The following newer build
has been checked at the protocol level but has **not** yet had a full end-to-end
hardware run, so it is listed separately from the verified unit above:

| Property     | Value                          |
| ------------ | ------------------------------ |
| Model        | **MB2000**                     |
| Firmware     | **Ver 8.0.4.5-20200729**       |
| Push Service | **Ver 2.0.30S** (ADMS PushV2)  |

What this implies per path:

- **Socket read/write/clock/control/realtime** — same model as the verified
  unit, over the same generic ZK6 wire format; expected to work unchanged.
- **Fingerprint `enroll()`** — the event stream was only ever observed on the
  6.60 unit, so it is unconfirmed here. Use the `$trace` hook (see
  [enrollment](#templates--fingerprint-enrollment)) to capture the real sequence
  before relying on it.
- **ADMS push (Push Service 2.0.30S)** — the device's `pushver` resolves to the
  **PushV2** generation, so it is served by the full PUSH-SDK *read* path
  (attendance, photos, biometric templates, user syncs, audit logs). The
  outbound command and config/registry layouts remain provisional (see
  [Limitations](#limitations)).

## Limitations

- **No remote face enrollment over the socket protocol.** Even on a device with
  a working face engine, the legacy binary protocol exposes **no network path to
  capture or enroll a face**. `CMD_STARTENROLL` drives only the *fingerprint*
  sensor, and buffered reads of face templates are rejected by the firmware.
  Faces must be enrolled at the device itself, via its on-screen menu.
  Fingerprints, by contrast, *are* network-enrollable via
  `templates()->enroll()`. (Over ADMS, capable firmware *can* push existing
  biometric templates — including faces — to you as `BiometricReceived` events,
  but that's the device uploading what it already has, not remote capture.)
- **Socket transport is TCP only.** `useUdp: true` (config `'udp' => true`)
  throws `ConnectionException::udpUnsupported()`. A UDP transport is not
  implemented yet.
- **Some ADMS outbound commands are provisional.** The attendance/photo/biodata
  *read* path and device registration are implemented and gated. The *write*
  path — power, `SET OPTIONS` (e.g. `syncTime`), and data writes (`upsertUser`,
  `deleteUser`, `pushTemplate`) — rides on best-effort wire layouts that have
  not yet been pinned against real hardware.

## Testing

```bash
vendor/bin/pest
```

The unit and Laravel suites use a fake transport and need no hardware. A
separate **Integration** suite talks to a real device and is skipped unless
`ZKTECO_DEVICE_HOST` is set:

```bash
ZKTECO_DEVICE_HOST=192.168.1.201 vendor/bin/pest --testsuite=Integration
```

Optional overrides: `ZKTECO_DEVICE_COMM_KEY` (default `0`) and
`ZKTECO_DEVICE_TIMEOUT` (seconds, default `5`). The interactive enrollment test
is additionally gated behind `ZKTECO_ENROLL_INTERACTIVE=1` because it needs a
person to physically press a finger on the sensor. The write-path tests are
reversible by design (throwaway probe users that are cleaned up afterwards).

## Design

Architecture decisions are recorded as ADRs in [`docs/adr/`](docs/adr/), and the
ubiquitous language lives in [`CONTEXT.md`](CONTEXT.md).

## License

MIT.
</content>
</invoke>
