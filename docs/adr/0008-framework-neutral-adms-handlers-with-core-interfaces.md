# ADMS HTTP handling is framework-neutral; persistence is core interfaces with bridge implementations

ADMS requires an inbound HTTP server, but ADR 0002 keeps the core framework-free
and ADR 0006 keeps the Laravel bridge dormant/optional. So the core (`src/Push/`)
owns the protocol logic as **framework-neutral handlers**: they accept primitive
request data (method, query params, raw body) and return primitive response data
(status, body), with no PSR-7/PSR-15 or Laravel dependency. The Laravel bridge
only maps `/iclock/*` routes onto those handlers. Likewise, the command queue and
device registry are **interfaces in the core** (`CommandQueue`, `DeviceRegistry`),
with Eloquent-backed implementations plus migrations living in the Laravel bridge.

## Consequences

- The core keeps zero runtime dependencies; a reader expecting PSR-15 should know
  the primitive-in/primitive-out shape is deliberate (avoids an HTTP contract in
  the core).
- ADMS is usable outside Laravel: an integrator wires their own routes and
  supplies a `CommandQueue`/`DeviceRegistry` implementation.
- Laravel is the only *provided* wiring; non-Laravel use is supported but DIY.
