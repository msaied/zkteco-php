<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Generations;

use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\ADMS\Handlers\CdataHandler;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\Exceptions\CommandException;

/**
 * A per-generation strategy for the ADMS family: it owns the whole protocol
 * vocabulary of a generation in both directions — which upload tables a device
 * speaks and how each decodes (read), the text a device reads back at handshake
 * and registration, and how a typed {@see DeviceCommand} renders to that
 * generation's wire syntax (write).
 *
 * The {@see CdataHandler} stays free of table knowledge by
 * delegating to the generation selected from the device's
 * {@see ProtocolGeneration}. Legacy and PUSH SDK each
 * implement this; PUSH SDK composes the legacy behaviour because it is a superset
 * of it, not a separate protocol (see CONTEXT.md and docs/adr/0012).
 */
interface Generation
{
    /**
     * Decode one upload table and route every decoded value to this generation's
     * sinks. `$table` is already upper-cased by the handler. A table this
     * generation does not own returns {@see IngestOutcome::ignored()} so the
     * handler treats it as a no-op (and leaves the stamp untouched).
     *
     * The whole {@see PushRequest} is passed, not just its body, because some
     * tables (e.g. `ATTPHOTO`) carry metadata in the query string.
     */
    public function ingest(string $table, PushRequest $request, string $serialNumber): IngestOutcome;

    /**
     * The config block a device of this generation reads after its `cdata`
     * handshake — where to resume each table and how to push.
     */
    public function configBlock(RegisteredDevice $device): string;

    /**
     * The body a device of this generation reads from `/iclock/registry`, the
     * dedicated PUSH-SDK registration endpoint.
     */
    public function registryBlock(RegisteredDevice $device): string;

    /**
     * Render a typed command intent into the ADMS instruction string this
     * generation's devices understand, ready to be enqueued (the `C:<id>:`
     * framing is added by the poll handler, not here). The same intent can
     * render differently per generation — a template is `FINGERTMP` on legacy
     * and `BIODATA` on PUSH SDK.
     *
     * @throws CommandException if this generation has no renderer for the intent
     */
    public function renderCommand(DeviceCommand $command): string;
}
