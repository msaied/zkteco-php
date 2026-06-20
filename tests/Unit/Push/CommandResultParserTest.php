<?php

declare(strict_types=1);

use ZkTeco\ADMS\Parsing\CommandResultParser;

beforeEach(function () {
    $this->parser = new CommandResultParser;
});

it('parses a single result line', function () {
    $results = $this->parser->parse('ID=12&Return=0&CMD=DATA');

    expect($results)->toHaveCount(1)
        ->and($results[0]->id)->toBe('12')
        ->and($results[0]->returnCode)->toBe(0)
        ->and($results[0]->command)->toBe('DATA')
        ->and($results[0]->succeeded())->toBeTrue();
});

it('parses multiple results, one per line', function () {
    $results = $this->parser->parse("ID=1&Return=0&CMD=DATA\nID=2&Return=-1&CMD=INFO");

    expect($results)->toHaveCount(2)
        ->and($results[0]->id)->toBe('1')
        ->and($results[1]->id)->toBe('2')
        ->and($results[1]->returnCode)->toBe(-1)
        ->and($results[1]->succeeded())->toBeFalse();
});

it('reads the fields case-insensitively and trims whitespace', function () {
    $results = $this->parser->parse('id=7 & return=0 & cmd=REBOOT');

    expect($results[0]->id)->toBe('7')
        ->and($results[0]->command)->toBe('REBOOT');
});

it('skips blank lines and lines without an id', function () {
    $results = $this->parser->parse("\nReturn=0&CMD=DATA\n\nID=5&Return=0\n");

    expect($results)->toHaveCount(1)
        ->and($results[0]->id)->toBe('5');
});

it('treats a missing or non-numeric return as a failure, not a silent success', function () {
    $results = $this->parser->parse("ID=9&CMD=DATA\nID=10&Return=oops");

    expect($results[0]->returnCode)->toBe(-1)
        ->and($results[0]->succeeded())->toBeFalse()
        ->and($results[1]->returnCode)->toBe(-1);
});

it('returns nothing for an empty body', function () {
    expect($this->parser->parse(''))->toBe([]);
});
