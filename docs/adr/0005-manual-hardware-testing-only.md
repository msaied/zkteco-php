# Protocol verification is manual, on real hardware only

We are porting the full ZK protocol including the firmware-variant-sensitive
template and enrollment code. We deliberately chose NOT to build automated
protocol tests (no captured-byte fixtures, no fake-device replay in CI).
Correctness is verified by running the library against real ZKTeco hardware by
hand. The recommended alternative — teeing socket bytes to fixtures during those
same manual sessions and replaying them in CI — was considered and explicitly
declined to minimise infrastructure.

## Status

accepted — with known risk.

## Consequences

- There is no regression net on the protocol layer. Refactors can silently break
  template/enrollment parsing; only a fresh manual hardware run will catch it.
- Contributors without the specific device cannot validate protocol changes.
- This contradicts the repo's general posture (Pest 4 present, "tests are more
  important"); higher-level/domain logic should still be unit-tested even though
  the wire protocol is not. Do not "add protocol tests" without revisiting this
  decision with the owner.
