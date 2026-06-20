# Grouped sub-service public API rather than a 1:1 pyzk port

pyzk exposes one `ZK` class with ~40 methods. We deliberately diverge: the public
API groups operations into focused sub-services reached from a `Device` entry
point — e.g. `$device->users()->all()`, `$device->attendance()->all()`,
`$device->control()->disable()`. This is more discoverable and far more testable
than a single god class, and matches idiomatic PHP SDK conventions. The cost is
that method names no longer map 1:1 to pyzk, so cross-referencing the source is
translation rather than a literal diff.

## Consequences

- A maintainer comparing against pyzk should map by capability group, not by
  method name.
- Sub-service boundaries (users, attendance, templates, realtime, device control,
  device info) become part of the ubiquitous language and the eventual ADMS
  adapter should reuse the same groupings where they apply.
