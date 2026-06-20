# The Generation strategy renders typed outbound commands

Through M4 the only way to send a device an ADMS command was a raw string handed
to `CommandQueue::enqueue($serial, 'REBOOT')`. The async lifecycle, persistence,
and acknowledgement were all built (M2), but every caller hand-wrote wire syntax
and the package's public write surface was the queue itself. The socket client,
by contrast, exposes a typed API (`$device->users()->save()`,
`->control()->restart()`). M5 gives ADMS the same: typed command *intents* a
caller expresses, rendered to wire syntax and enqueued.

## Rendering lives on the `Generation`, not on the command

A command's wire form is generation-specific — a template push is `FINGERTMP` on
legacy and `BIODATA` on PUSH SDK — exactly like the *read* vocabulary M4 already
put behind the `Generation` strategy (`ingest`/`configBlock`/`registryBlock`). So
rather than give each command a `toWire()` that would have to branch on
generation internally, the `Generation` gains a fourth method,
`renderCommand(DeviceCommand): string`, and now owns a generation's **entire
protocol vocabulary in both directions**. This is the symmetric mirror of the
read path and keeps every per-generation divergence in one object.

A `DeviceCommand` is therefore a pure marker value with no wire knowledge of its
own — inspectable, loggable, and trivially testable — while the syntax stays with
the strategy that owns the protocol.

## Intents are sealed; rendering is a `match` with a throwing default

Every intent lives in `ADMS\Commands\Intents` and each generation renders them
with a `match (true)` on the instance type — the write mirror of `ingest()`'s
`match` on the table string. The difference is the default arm: an unrecognised
*table* is a tolerated no-op (`IngestOutcome::ignored()`), but an unrecognised
*command* is a programmer error (an intent added without a renderer), so it throws
`CommandException`. `LegacyGeneration` renders the full set; `PushSdkGeneration`
overrides only what genuinely diverges and delegates the rest to the composed
legacy generation — the same composition-not-inheritance shape as M4.

## Only the template command actually diverges

Scoping anticipated PUSH SDK overriding two data commands (user upsert and
template). In practice the `USERINFO` upsert is wire-identical across generations,
so `PushSdkGeneration` overrides **only** `PushTemplate` (`FINGERTMP` → `BIODATA`)
and delegates the upsert to legacy. Manufacturing a second override to match the
sketch would have been cargo-cult divergence. If M6's capture reveals a real
`USERINFO` difference, it earns an override then — which is precisely what the
strategy seam is for.

## `DeviceCommander` is the typed entry point

`DeviceCommander::dispatch($serial, DeviceCommand)` resolves the device's
generation from the registry, renders the intent, and enqueues the result. It is
framework-neutral core; the bridge fronts it with `PendingDeviceCommands`
(returned by `ZkTeco::push($serial)`) for the fluent
`ZkTeco::push($sn)->reboot()` ergonomics. Dispatching to a serial that has never
registered **throws** rather than queuing an instruction no device will drain —
the better failure mode than a silent queue-to-nowhere.

## Consequences

- The `Generation` interface grows `renderCommand()`; `CdataHandler`, the queue,
  and the `getrequest` `C:<id>:` framing are untouched — `renderCommand()` returns
  just the `<cmd>` payload.
- Two new `ErrorCode`s (`unknown_device`, `unsupported_command`) and a
  `CommandException` join the public contract, keeping "every exception extends
  `ZkException`" intact.
- The control verbs (`REBOOT`, `CLEAR *`, `DATA QUERY`, `DATA DELETE USERINFO`) are
  the widely-supported ADMS forms. The `SET OPTIONS DateTime` time encoding, the
  `USERINFO`/`FINGERTMP`/`BIODATA` data layouts, and the best-effort
  `POWEROFF`/`ENABLE`/`DISABLE` verbs (which have no canonical ADMS equivalent)
  are provisional until pinned against a real device (see docs/adr/0005). M6
  validates them — M5 introduces no unvalidated risk the M4 parsers did not
  already carry.
- The `SET OPTIONS DateTime` encoder is kept inside `LegacyGeneration` rather than
  imported from the ZK socket stack's `TimeCodec`, so the two adapters stay
  decoupled (see docs/adr/0007).
