<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\ADMS\Commands\EmptyCommandQueue;
use ZkTeco\ADMS\Commands\QueuedCommand;
use ZkTeco\ADMS\Handlers\GetrequestHandler;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\Tests\Support\InMemoryCommandQueue;
use ZkTeco\Tests\Support\InMemoryDeviceRegistry;
use ZkTeco\Tests\Support\StaticCommandQueue;

it('answers an empty queue with a keep-alive OK and marks the device seen', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $handler = new GetrequestHandler($registry, new EmptyCommandQueue);

    $response = $handler->handle(new PushRequest('GET', 'iclock/getrequest', ['SN' => 'SN1']));

    expect($response->status)->toBe(200)
        ->and($response->body)->toBe('OK')
        ->and($registry->seen)->toContain('SN1');
});

it('renders pending commands as C-lines', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $queue = new StaticCommandQueue([
        new QueuedCommand('1', 'SN1', 'DATA QUERY USERINFO'),
        new QueuedCommand('2', 'SN1', 'REBOOT'),
    ]);
    $handler = new GetrequestHandler($registry, $queue);

    $response = $handler->handle(new PushRequest('GET', 'iclock/getrequest', ['SN' => 'SN1']));

    expect($response->body)->toBe("C:1:DATA QUERY USERINFO\nC:2:REBOOT");
});

it('marks drained commands as sent so the next poll does not re-send them', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $queue = new InMemoryCommandQueue;
    $command = $queue->enqueue('SN1', 'REBOOT');
    $handler = new GetrequestHandler($registry, $queue);

    $first = $handler->handle(new PushRequest('GET', 'iclock/getrequest', ['SN' => 'SN1']));

    expect($first->body)->toBe("C:{$command->id}:REBOOT")
        ->and($queue->statusOf($command->id))->toBe(CommandStatus::Sent);

    $second = $handler->handle(new PushRequest('GET', 'iclock/getrequest', ['SN' => 'SN1']));

    expect($second->body)->toBe('OK');
});
