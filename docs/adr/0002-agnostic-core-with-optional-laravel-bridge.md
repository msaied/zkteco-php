# Framework-agnostic core with an optional Laravel bridge

The package targets PHP broadly, not just Laravel, and the ZK binary protocol is
pure socket I/O with no need for a framework. We therefore split the package: a
framework-agnostic core in `src/` (the protocol client and domain, no
`illuminate/*` dependency) and a thin, optional Laravel bridge (ServiceProvider,
Facade, config) layered on top. This maximises reach (plain PHP, Symfony,
Laravel) and gives ADMS's eventual inbound HTTP endpoints a natural home as
routes in the bridge, without coupling the protocol to the framework.

## Consequences

- The current `laravel/laravel` application skeleton must be restructured into a
  library layout (`src/` with a dedicated PSR-4 namespace, `type: library`).
- Laravel-specific tests run against `orchestra/testbench`; the core is tested as
  plain PHP.
- Rejected: a Laravel-only package — faster to build but excludes non-Laravel
  users and couples the socket protocol to the framework.
