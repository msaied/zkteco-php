# The ADMS write path deliberately diverges from the ZK services

A tempting goal is one swappable service interface across both adapters
(`users()->save()` works whether you're on ZK or ADMS). We reject that for the
write path, because ADMS's timing and identity are genuinely different and hiding
that produces a leaky API:

- **Identity.** A ZK device is a host/port we initiate to (the `Device` value
  object). An ADMS device dials in and is keyed by serial number, carrying
  generation, capabilities, last-seen, and stamps. We model it as a separate
  **Registered device** concept rather than overloading `Device`.
- **Timing.** ZK commands are synchronous and return a result. ADMS commands are
  enqueued and run on the device's next poll (seconds–minutes later), with the
  outcome arriving via an ack. The ADMS write API is therefore **explicitly
  async**: verbs enqueue and return a queued-command handle; results surface
  through a `CommandAcknowledged` event. No fire-and-forget pretending to be sync,
  no block-and-wait simulating sync.

The *read* path stays unified — both adapters emit `PunchReceived` — because
ingestion has no such timing mismatch.

## Consequences

- No shared write interface between adapters; callers pick the adapter knowingly.
- `Device` stays ZK-only; ADMS gets `RegisteredDevice` + a `DeviceRegistry`.
- The async result contract (`CommandAcknowledged`) must be part of the public API
  from the start, not bolted on.
