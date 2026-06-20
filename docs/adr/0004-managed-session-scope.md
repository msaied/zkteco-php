# Managed session scope as the primary connection API

pyzk leaves connect/disconnect and disable/enable entirely to the caller. That is
a footgun: a crash after `disableDevice()` physically locks the terminal so
nobody can punch, and a forgotten `disconnect()` leaks the device session. Our
primary API is therefore a managed scope — `$device->session(fn ($s) => ...)` —
that guarantees re-enable and disconnect run via `try/finally` even when the body
throws. A lower-level explicit `connect()/disconnect()` remains available for
long-lived or advanced use.

## Consequences

- Deviates from pyzk's manual lifecycle; a maintainer expecting the pyzk pattern
  will find the safe closure form is the default.
- The realtime listener, which holds a long-lived connection, needs its own
  lifecycle handling rather than the short-lived `session()` scope.
