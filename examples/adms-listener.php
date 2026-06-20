<?php

declare(strict_types=1);

/*
 * Live ADMS listener demo — watch a real device go through the flow.
 *
 *   1. Start it (binds on all interfaces so the device can reach it):
 *
 *        php -S 0.0.0.0:8080 examples/adms-listener.php
 *
 *   2. On the device: Comm → Cloud Server / ADMS Setup →
 *        Server Address = <this machine's LAN IP>   Port = 8080
 *      (disable "Enable Domain Name"). It will handshake, then push on each punch.
 *
 *   3. Watch this terminal. A new device shows as PENDING and its punches are
 *      held. Approve it from another terminal to let attendance flow:
 *
 *        php examples/adms-approve.php <SERIAL>
 *
 * It wires the package's real core (router, handlers, parser) to a small
 * SQLite-backed registry in examples/adms-demo.sqlite. Delete that file to reset.
 */

require_once __DIR__.'/DemoRegistry.php';

use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\ADMS\Commands\EmptyCommandQueue;
use ZkTeco\ADMS\Handlers\CdataHandler;
use ZkTeco\ADMS\Handlers\DevicecmdHandler;
use ZkTeco\ADMS\Handlers\GetrequestHandler;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushRouter;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Registry\Negotiator;
use ZkTeco\Values\AttendanceRecord;

const DB = __DIR__.'/adms-demo.sqlite';

/** Narrate to the server console (stderr), not the HTTP response. */
function say(string $line): void
{
    fwrite(STDERR, $line.PHP_EOL);
}

function paint(string $code, string $text): string
{
    return "\033[{$code}m{$text}\033[0m";
}

$registry = new DemoRegistry(DB, autoRegister: true);

$sink = new class implements AttendanceSink
{
    /** @var list<AttendanceRecord> */
    public array $records = [];

    public function receive(AttendanceRecord $record, string $serialNumber): void
    {
        $this->records[] = $record;
    }
};

$router = new PushRouter(
    $registry,
    new CdataHandler($registry, new Negotiator, new AttlogParser, $sink),
    new GetrequestHandler($registry, new EmptyCommandQueue),
    new DevicecmdHandler($registry),
);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$body = file_get_contents('php://input') ?: '';

$query = [];
foreach ($_GET as $key => $value) {
    if (is_scalar($value)) {
        $query[$key] = (string) $value;
    }
}

$serial = $query['SN'] ?? $query['sn'] ?? '(no serial)';
$endpoint = strtolower(basename($path));
$at = date('H:i:s');

$response = $router->dispatch(new PushRequest($method, $path, $query, $body));

if ($response->status === 400) {
    say("[$at] ".paint('31', '✗ BAD REQUEST').'  (missing serial number)');
} elseif ($response->status === 401) {
    say("[$at] ".paint('31', '⛔ REJECTED')."   {$serial}  (blocked or not admitted)");
} elseif ($endpoint === 'cdata' && $method === 'GET') {
    $status = $registry->find($serial)?->status->value ?? 'unknown';
    $badge = $status === 'approved' ? paint('32', 'APPROVED') : paint('33', 'PENDING');
    say("[$at] ".paint('36', '📡 HANDSHAKE')."  {$serial}  → [{$badge}]");
    if ($status !== 'approved') {
        say('             '.paint('33', "approve with:  php examples/adms-approve.php {$serial}"));
    }
} elseif ($endpoint === 'cdata' && $method === 'POST' && strtoupper($query['table'] ?? '') === 'ATTLOG') {
    if ($response->status === 503) {
        say("[$at] ".paint('33', '⏸ HELD')."       {$serial}  attendance held — device told to retry (pending approval)");
    } else {
        say("[$at] ".paint('32', '✅ INGESTED')."   {$serial}  ".count($sink->records).' punch(es):');
        foreach ($sink->records as $record) {
            say('             '.paint('32', '•')."  user {$record->userId}  {$record->recordedAt->format('Y-m-d H:i:s')}  {$record->punchState->name} / {$record->verifyMode->name}");
        }
    }
} elseif ($endpoint === 'getrequest') {
    say("[$at] ".paint('90', '· poll')."        {$serial}");
} elseif ($endpoint === 'devicecmd') {
    say("[$at] ".paint('90', '· cmd-result')."  {$serial}");
}

http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name.': '.$value);
}
echo $response->body;
