# OPERLOG is a demultiplexed upload stream, fanned out to per-type sinks

M3 adds the remaining legacy `cdata` tables: operation logs, user sync, and punch
photos. The shape that surprises is `OPERLOG`. Unlike `ATTLOG` — one row, one
record type — a legacy `OPERLOG` body is a **multiplexed stream**: each row is
prefixed with a tag word (`OPLOG`, `USER`, `FP`, `FACE`, `USERPIC`, …), and the
legacy generation carries a device's **USERINFO inside it** as `USER` rows rather
than on a table of its own. There is no separate "USERINFO upload" to parse on
this generation; treating it as one would mean a parser with nothing distinct to
do.

So we model the upload honestly:

- **One `OperlogParser` demultiplexes the stream**, returning an `OperlogBatch` of
  the two kinds this milestone owns — operation log entries and User records. Tags
  it does not own (`FP`/`FACE`/`USERPIC`/`BIODATA` — biometric/photo rows that
  belong to a later milestone) are skipped, not guessed at. A standalone
  `table=USERINFO` upload, where a firmware sends one, routes through the same
  user extraction.
- **The read path fans out to per-type sinks.** Rather than widen `AttendanceSink`
  or invent a unified upload sink, each decoded kind gets its own framework-neutral
  seam — `OperationLogSink`, `UserSink`, `AttendancePhotoSink` — mirroring
  `AttendanceSink` exactly (see [ADR 0008](0008-framework-neutral-adms-handlers-with-core-interfaces.md)).
  The bridge binds each to an event (`OperationLogged`, `UserReceived`,
  `AttendancePhotoReceived`).

Identity follows the same rule as the rest of ADMS: a pushed `User` is keyed by
its `userId` (the employee PIN); the device-local `uid` is not in a push row, so
it arrives as `0` and is never taken from the PIN (see `CONTEXT.md`). All
ingestion stays gated on device approval, exactly as `ATTLOG` is — an unapproved
device's operation logs and user sync are held too, not just its attendance (see
[ADR 0010](0010-adms-trust-but-gate-security-posture.md)).

Field layouts (`OPLOG` columns, `USER` key set, `ATTPHOTO` framing) are
firmware-sensitive and provisional until pinned against a real capture (see
[ADR 0005](0005-manual-hardware-testing-only.md)); the parsers are written tolerant
— the `OPLOG` row anchors on its unambiguous timestamp column rather than a fixed
field count — so re-pinning is a small change, not a rewrite.

## Consequences

- `OPERLOG` and `USERINFO` share one parser and one stamp-advancing code path; the
  parser returns a batch of mixed kinds rather than a single list.
- Four read-path sinks now exist in the core (attendance, operation log, user,
  photo); each must be bound in the bridge or `CdataHandler` cannot resolve.
- Biometric template rows in the `OPERLOG` stream are deliberately dropped for now;
  they arrive with the PUSH-SDK `BIODATA`/`RTLOG` work in M4.
