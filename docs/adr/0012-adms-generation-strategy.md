# A per-generation strategy owns table decoding and the config/registry text

M4 adds the **PUSH SDK** generation: a superset of legacy ADMS that adds the
`RTLOG` (real-time attendance) and `BIODATA` (biometric template) tables and a
dedicated `/iclock/registry` endpoint (see CONTEXT.md "Protocol generation" /
"PUSH SDK"). Through M3 the `CdataHandler` hardcoded one table set, one `match`
over tables, and one config block. There was nowhere for "what PUSH SDK does
differently" to live, and bolting `RTLOG`/`BIODATA` arms onto the same handler
would have made it grow a second protocol's worth of knowledge.

So the table knowledge moves behind a **`Generation` strategy**, one
implementation per generation, selected from the device's
`ProtocolGeneration`. The handler keeps only the protocol envelope — note the
device alive, gate on approval, advance the per-table stamp, shape the reply —
and delegates the rest:

- **`Generation::ingest($table, $request, $serial)`** decodes one upload table
  and routes the decoded values to that generation's own per-type sinks. It is the
  strategy that holds the sinks, not the handler.
- **`Generation::configBlock()` / `registryBlock()`** produce the text a device of
  that generation reads back at handshake and registration.

## The heterogeneous-sink seam

The five upload tables decode into five different value types bound for four
different sinks (attendance, operation log, user, photo, biometric). A strategy
that "decodes and routes" cannot hand the handler a single decoded thing. So
`ingest()` returns an **`IngestOutcome`** — just `(handled, count)`. The handler
maps `handled` onto whether to advance the stamp (an un-owned table is a no-op, so
its stamp is untouched, preserving the M1–M3 behaviour) and `count` onto the
`OK: <n>` reply, while staying ignorant of records, sinks, and table layouts.

## PUSH SDK composes legacy, it does not subclass it

`PushSdkGeneration` reuses legacy decoding for the shared tables and adds two of
its own, so it **holds** a `LegacyGeneration` and delegates to it for everything
it does not own. Composition, not inheritance: the core is all-`final`, the
relationship is behavioural delegation rather than type substitution, and `RTLOG`
attendance flows to the same `AttendanceSink` as legacy `ATTLOG`, keeping the read
path unified (see docs/adr/0009).

## Three generation values, two strategies

`ProtocolGeneration` has three cases (`Legacy`, `PushV2`, `PushV3`) but PUSH SDK
is one behaviour. A `GenerationSelector` holds a map keyed by the generation's
backing value and supplied by the bridge, so both `PushV2` and `PushV3` point at
the single `PushSdkGeneration` instance, with `Legacy` as the fallback for any
unmapped value — the same "unrecognised means legacy" stance the
`ProtocolGeneration::fromPushVersion()` negotiation already takes. When a future
firmware makes V3 genuinely diverge, it earns **a new map entry / a new instance**,
not a subclass.

## Approval gate runs before the unknown-table no-op

With ingestion behind the strategy, the approval gate now runs before a table is
judged owned-or-not. A *pending* device posting an *unknown* table therefore now
gets `503` (held) rather than `200`. This is the more correct reading of ADR 0010
/ 0011 — everything from an unapproved device is held — and no prior behaviour
relied on the old ordering.

## Consequences

- `CdataHandler` shrinks to `(DeviceRegistry, Negotiator, GenerationSelector)`; the
  per-table decoding and the config block move into `LegacyGeneration`.
- A fifth read-path sink (`BiometricSink`) now exists in the core and must be bound
  in the bridge, alongside the two `Generation` strategies and the selector.
- `RTLOG` joins the unified read path (`PunchReceived`); `BIODATA` gets its own
  PIN-keyed `BiometricTemplate` value and `BiometricReceived` event, diverging from
  the ZK slot-keyed `Template` (see docs/adr/0009 and CONTEXT.md).
- The `RTLOG`/`BIODATA`/`registry` byte layouts are firmware-sensitive and
  provisional until pinned against a real capture (see docs/adr/0005); the parsers
  are written tolerant so re-pinning is a small change, not a rewrite.
