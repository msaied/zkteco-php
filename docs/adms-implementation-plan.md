# ADMS Adapter — Implementation Plan

Status: planned (no code yet). Decisions captured in ADR 0007–0010 and `CONTEXT.md`.

ADMS is the device-initiated HTTP push family, the inverse of the ZK socket
protocol. This plan adds it as a **peer adapter** that shares only the domain
layer with the existing ZK stack. See [ADR 0007](adr/0007-adms-is-a-peer-adapter-sharing-the-domain.md).

## Decisions (grilled)

| Topic | Decision | Source |
|---|---|---|
| Protocol shape | One **ADMS family** with a `ProtocolGeneration` axis (Legacy / PushV2 / PushV3), not two adapters | `CONTEXT.md` |
| Architecture | **Peer subsystem** sharing the domain, not the `Transport` abstraction | ADR 0007 |
| HTTP boundary | **Framework-neutral handlers** in core (primitive request/response, no PSR/Laravel) | ADR 0008 |
| Persistence | `CommandQueue` + `DeviceRegistry` **interfaces in core**, Eloquent impls in bridge | ADR 0008 |
| Identity | Separate **`RegisteredDevice`** (serial-number keyed); `Device` stays ZK-only | ADR 0009 |
| Write path | **Explicitly async** (enqueue → handle; result via `CommandAcknowledged`) | ADR 0009 |
| Read path | **Unified** — both adapters emit `PunchReceived` | ADR 0009 |
| Security | **Trust-but-gate**: allowlist, no `Shell` command, TLS is the deployment's job | ADR 0010 |
| Entry point | **Minimal router** + Laravel route group; no grouped sub-services in v1 | grilled |
| Queue seam | `CommandQueue` **defined in v1, drained empty** | grilled |

## v1 scope — Inbound Attendance MVP

**In:** registration/handshake, `ATTLOG` ingestion → `PunchReceived`, `getrequest`
returning empty (keep-alive), `devicecmd` accepted-as-noop. **Legacy** generation.

**Deferred:** all outbound commands, `OPERLOG`/`USERINFO`/photos, `BIODATA`/`RTLOG`,
PUSH v2/v3 strategies.

Rationale: smallest slice that still exercises the whole pipeline — routing,
registry, allowlist, stamps, generation negotiation — before surface grows.

## Structure

```
src/Push/                          ADMS stack — framework-neutral, zero runtime deps
   ├── Http/        PushRequest, PushResponse (primitive DTOs), PushRouter
   ├── Handlers/    CdataHandler, GetrequestHandler, DevicecmdHandler
   ├── Parsing/     AttlogParser
   ├── Registry/    DeviceRegistry (iface), RegisteredDevice, Capabilities,
   │                ProtocolGeneration (enum), Stamp, Negotiator
   └── Commands/    CommandQueue (iface)        ← defined now, drained empty in v1
        ↘  src/Values/AttendanceRecord + Enums + PunchReceived  ↙   shared domain
src/Laravel/                       thin bridge (ADR 0006)
   ├── routes: /iclock/cdata | /iclock/getrequest | /iclock/devicecmd → PushController
   ├── EloquentDeviceRegistry, EloquentCommandQueue
   └── migrations: zkteco_devices, zkteco_commands
```

## Request flow (v1)

1. `GET /iclock/cdata?SN=…&options=all&pushver=…` → `CdataHandler::handshake()`:
   `Negotiator` reads `pushver`/capability flags → `DeviceRegistry::register()`
   (allowlist-gated) → returns config block (stamps, delay, transflag).
2. `POST /iclock/cdata?SN=…&table=ATTLOG&Stamp=…` → `CdataHandler::receiveData()`
   → `AttlogParser` → `AttendanceRecord[]` → fire `PunchReceived` per record →
   `DeviceRegistry::updateStamp()` → reply `OK`.
3. `GET /iclock/getrequest?SN=…` → `GetrequestHandler`: `heartbeat()`,
   `CommandQueue::pending()` → empty → reply `OK` (keep-alive).
4. `POST /iclock/devicecmd` → `DevicecmdHandler`: accept, noop, `OK` (real ack in M2).

Unknown serial number at any endpoint → quarantine/reject, never auto-trust
([ADR 0010](adr/0010-adms-trust-but-gate-security-posture.md)).

## New domain pieces

- `RegisteredDevice` — SN, `ProtocolGeneration`, `Capabilities`, last-seen, stamps.
- `ProtocolGeneration` enum — `Legacy` (v1), `PushV2`/`PushV3` (later).
- `Capabilities` — bio/photo/face flags from the handshake.
- `Stamp` — per-table watermark.
- Events: reuse `PunchReceived`; add `DeviceRegistered`. `CommandAcknowledged`
  arrives with the M2 write path.

## Testing

Per [ADR 0005](adr/0005-manual-hardware-testing-only.md) and the env-gated suite:

- **Unit:** framework-neutral handlers are pure (primitive in → primitive out) —
  test against captured request fixtures, no HTTP, no Laravel.
- **Capture-first:** before writing `AttlogParser`, point device `192.168.1.195`
  at a throwaway endpoint and capture a real `ATTLOG` POST to lock the field
  order. Free specs are unreliable on byte layout; the device is ground truth.

## Milestones

- **M1** ✅ — this plan (Inbound Attendance MVP).
- **M2** ✅ — outbound command queue + explicitly-async write API + `CommandAcknowledged`
  + real `devicecmd` ack handling.
- **M3** ✅ — `OPERLOG`/`USERINFO`/`ATTPHOTO` parsers. `OPERLOG` is a demultiplexed
  stream that carries legacy USERINFO inline; decoded kinds fan out to per-type
  sinks (`OperationLogSink`/`UserSink`/`AttendancePhotoSink`) dispatching
  `OperationLogged`/`UserReceived`/`AttendancePhotoReceived`. See
  [ADR 0011](adr/0011-operlog-is-a-demultiplexed-upload-stream.md).
- **M4** ✅ — PUSH SDK (v2/v3): a per-generation `Generation` strategy, selected from
  `ProtocolGeneration` by a `GenerationSelector`, now owns table decoding and the
  config/registry text; `CdataHandler` keeps only the protocol envelope. `RTLOG`
  parses to `AttendanceRecord` and joins the unified read path (`PunchReceived`);
  `BIODATA` parses to a new PIN-keyed `BiometricTemplate` fanned out to a
  `BiometricSink`/`BiometricReceived`. PUSH SDK composes legacy (it is a superset)
  and adds the two tables; a new `GET /iclock/registry` endpoint registers PUSH-SDK
  devices. Byte layouts stay provisional until a capture (ADR 0005). See
  [ADR 0012](adr/0012-adms-generation-strategy.md).
- **M5** ✅ — Typed outbound commands. The `Generation` strategy gains
  `renderCommand(DeviceCommand)`, so it now owns each generation's *write*
  vocabulary as well as its read vocabulary. Caller-facing `DeviceCommand` intents
  (`Reboot`, `ClearData`, `SyncTime`, `DeleteUser`, `UpsertUser`, `PushTemplate`, …
  in `ADMS\Commands\Intents`) are rendered to wire syntax by the device's
  generation and enqueued by a framework-neutral `DeviceCommander`, fronted by the
  bridge as `ZkTeco::push($serial)->reboot()`. Only `PushTemplate` genuinely
  diverges (`FINGERTMP` vs `BIODATA`); everything else is rendered by the composed
  legacy generation. Control verbs are stable; the data layouts, the `SET OPTIONS`
  time encoding, and the no-canonical-verb `POWEROFF`/`ENABLE`/`DISABLE` are
  provisional until M6. See [ADR 0013](adr/0013-adms-typed-outbound-commands.md).
- **M6** ⛔ blocked — Hardware layout validation. Pin the provisional PUSH-SDK byte
  layouts against a real device: capture genuine `RTLOG`/`BIODATA`/`registry`/
  `devicecmd` payloads and the `USERINFO`/`BIODATA`/`FINGERTMP` command echoes,
  lock the field orders, and flip the tolerant parsers **and** the
  `UpsertUser`/`PushTemplate`/`SyncTime`/`PowerOff`/`Enable`/`Disable` renderers from
  provisional → verified, with captured fixtures. **Cannot start with current
  hardware**: the bench device (192.168.1.195, legacy MB2000) does not speak PUSH
  SDK, so it never emits `RTLOG`/`BIODATA` nor accepts the `BIODATA` command form.
  Needs a PUSH-SDK-capable device on the LAN (ADR 0005).
