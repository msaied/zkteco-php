# Changelog

All notable changes to `msaied/zkteco` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-06-20

### Added

- MIT `LICENSE` file (the license was already declared in `composer.json`).
- Detailed `fingerIndex` mapping documentation in the README and the `Template`
  value object.

### Changed

- Refactored the ADMS parsers to share row- and byte-level parsing utilities
  (internal; no public API change).
- Trimmed the Composer dist tarball via `.gitattributes` `export-ignore` — tests,
  docs, examples, and dev tooling are no longer shipped to consumers.

### Removed

- Stopped tracking `composer.lock`, as is conventional for a library.

## [0.1.0] - 2026-06-20

Initial release.

### Added

- **TCP socket client** (`ZkTeco\TCP`) — a port of [pyzk](https://github.com/fananimi/pyzk).
  A single `Device` entry point exposes grouped sub-services: `users()`,
  `attendance()`, `templates()`, `control()`, `info()`, and `realtime()`.
- **Managed session scope** that disables the device for the duration of the
  work and guarantees it is re-enabled and disconnected even when the body throws.
- **Realtime punch streaming** via a PHP `Generator`, and **interactive
  fingerprint enrollment** driven from your code.
- **Template handling** — read, upload, and enrollment.
- **ADMS push protocol** (`ZkTeco\ADMS`) — HTTP endpoints that ingest
  attendance, attendance photos, biometric templates, user syncs, and audit
  logs, behind a **trust-but-gate** device admission model (strict allowlist or
  accept-then-approve). Includes a demultiplexed upload stream for operation
  logs and attendance photos, and PUSH-SDK generation/parsing for biometric data.
- **Typed outbound ADMS commands** (`DeviceCommand` / `DeviceCommander`) —
  queue `reboot`, `syncTime`, `upsertUser`, `pushTemplate`, etc. for a device to
  run on its next poll; outcomes surface as events.
- **Immutable value objects** (`User`, `AttendanceRecord`, `Template`,
  `OperationLog`, `AttendancePhoto`, `BiometricTemplate`) and typed **enums**
  (`Privilege`, `PunchState`, `VerifyMode`, `OperationType`).
- **Optional Laravel bridge** — auto-discovered service provider, `ZkTeco`
  facade, config, three artisan commands, events, Eloquent models, and an
  asynchronous command queue with Eloquent persistence. Dormant when
  `illuminate/*` is absent.
- Unit test suite plus an env-gated integration suite for real-device verification.

### Notes

- The socket protocol is verified end-to-end against real hardware.
- The ADMS read path is fully implemented; some outbound ADMS command layouts
  are still provisional (see the README's Limitations section).

[Unreleased]: https://github.com/msaied/zkteco-php/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/msaied/zkteco-php/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/msaied/zkteco-php/releases/tag/v0.1.0
