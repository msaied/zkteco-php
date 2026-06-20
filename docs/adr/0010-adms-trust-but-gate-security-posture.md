# ADMS security posture: trust-but-gate, no Shell command, TLS is the deployment's job

ADMS authenticates devices by serial number only, which is weak: anyone who learns
a serial number could POST attendance, and the protocol's `Shell` command is remote
code execution on the device. Our posture:

- **Gate, don't auto-trust.** Admission is separate from approval, in one of two
  postures (config `adms.auto_register`):
  - *Strict* (default): only allowlisted serials are admitted, and they are
    approved on sight. Everything else is rejected and never recorded.
  - *Open*: any device may dial in and is recorded, but an unknown one lands
    **pending** — visible, yet its attendance is **held** (the upload is answered
    with a retry, not ingested, and its stamp is not advanced) until an operator
    approves it (`zkteco:approve <serial>`). Allowlisted serials still approve on
    sight; a device can be **blocked** to refuse it outright.
  Recording a device is never the same as trusting its data — this is how
  "accept all, but choose which to add" stays safe.
- **No `Shell` command, ever.** It is omitted from the command builder entirely, so
  the package cannot be used to run arbitrary code on a device.
- **TLS is the integrator's responsibility.** We do not terminate TLS in-package
  (that belongs at the proxy/load balancer); we document that ADMS must be deployed
  behind HTTPS.

## Consequences

- A reader who sees `Shell` in the ADMS protocol should know its absence here is
  deliberate, not an oversight.
- The `DeviceRegistry` carries a `pending`/`approved`/`blocked` status, not just
  "seen before" — admission and approval are distinct gates.
- Holding a pending device's data depends on the device retrying on a non-`OK`
  reply; the exact retry semantics are firmware-sensitive and provisional until
  pinned against a capture (see docs/adr/0005).
- Plaintext-HTTP deployments are an explicitly documented misuse, not a supported
  mode.
