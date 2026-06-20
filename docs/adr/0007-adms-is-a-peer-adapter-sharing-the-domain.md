# ADMS is a peer adapter sharing the domain, not the transport abstraction

ADR 0001 framed ADMS as fitting a "transport seam," but the `Transport` interface
(`src/Connection/Transport.php`) models a client-initiated socket — `connect` /
`send` / `receive` / `close`. ADMS inverts all of that: it is an inbound HTTP
server the device dials into, with async command timing (enqueue now, ack later).
Forcing ADMS behind `Transport` would make `send()` mean "enqueue" and leave
`receive()` dead — a misleading abstraction. We therefore build ADMS as a **peer
subsystem** (`src/Push/`: HTTP handlers, command queue, device registry) that
shares the domain layer — Values, enums, and the `PunchReceived` event — with the
ZK-protocol stack. The real seam ADR 0001 delivered is a *domain* seam, not a
*transport* seam.

## Consequences

- Refines ADR 0001: "transport seam" should be read as "domain seam." The two
  adapters share `src/Values/` + enums + events and nothing else.
- `Transport` and `Session` stay specific to the ZK protocol; ADMS never
  implements them.
- The ZK-protocol stack is untouched by ADMS work.
