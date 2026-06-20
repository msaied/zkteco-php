# Port the pyzk ZK protocol first; treat ADMS as a future adapter

We are building a PHP package for ZKTeco devices. The ZK protocol (pyzk's binary
socket protocol, client-initiated) and ADMS (HTTP push, device-initiated) are
inverse integration models that share only a domain (users, attendance,
templates, device control) — not transport, control flow, or timing. Rather than
build both transports at once, we port the ZK protocol first and shape the domain
(entities plus a transport seam) so an ADMS adapter can be added later without
reworking the core. This ships the user's stated goal fastest and avoids
committing to reconcile sync-pull vs async-push semantics before the core exists.

## Consequences

- The domain layer (User, Attendance, Template, Device control) must stay free of
  ZK-protocol socket assumptions so ADMS can reuse it.
- ADMS-only concerns (an inbound HTTP server, command queueing, device polling)
  are explicitly out of initial scope.
