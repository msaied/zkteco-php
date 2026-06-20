<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\ADMS\Handlers\DevicecmdHandler;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Parsing\CommandResultParser;
use ZkTeco\Tests\Support\InMemoryCommandQueue;
use ZkTeco\Tests\Support\InMemoryDeviceRegistry;

function devicecmdHandler(InMemoryDeviceRegistry $registry, InMemoryCommandQueue $queue): DevicecmdHandler
{
    return new DevicecmdHandler($registry, $queue, new CommandResultParser);
}

it('acknowledges a reported command and marks the device seen', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $queue = new InMemoryCommandQueue;
    $command = $queue->enqueue('SN1', 'REBOOT');
    $queue->markSent($command->id);

    $response = devicecmdHandler($registry, $queue)->handle(
        new PushRequest('POST', 'iclock/devicecmd', ['SN' => 'SN1'], "ID={$command->id}&Return=0&CMD=DATA"),
    );

    expect($response->status)->toBe(200)
        ->and($queue->statusOf($command->id))->toBe(CommandStatus::Acknowledged)
        ->and($queue->acknowledged)->toHaveCount(1)
        ->and($queue->acknowledged[0]->succeeded())->toBeTrue()
        ->and($registry->seen)->toContain('SN1');
});

it('acknowledges every result in a multi-line report', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $queue = new InMemoryCommandQueue;
    $first = $queue->enqueue('SN1', 'REBOOT');
    $second = $queue->enqueue('SN1', 'DATA QUERY USERINFO');

    devicecmdHandler($registry, $queue)->handle(new PushRequest(
        'POST',
        'iclock/devicecmd',
        ['SN' => 'SN1'],
        "ID={$first->id}&Return=0\nID={$second->id}&Return=0",
    ));

    expect($queue->statusOf($first->id))->toBe(CommandStatus::Acknowledged)
        ->and($queue->statusOf($second->id))->toBe(CommandStatus::Acknowledged);
});

it('tolerates a result for an unknown command id', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $queue = new InMemoryCommandQueue;

    $response = devicecmdHandler($registry, $queue)->handle(
        new PushRequest('POST', 'iclock/devicecmd', ['SN' => 'SN1'], 'ID=999&Return=0'),
    );

    expect($response->status)->toBe(200)
        ->and($queue->acknowledged)->toBeEmpty();
});
