# Single Composer package with a dormant Laravel bridge

Implementing ADR-0002's core/bridge split as packaging: we ship one Composer
package (`<vendor>/zkteco-php`, root namespace `ZkTeco\`, PHP floor 8.3) rather
than two. The pure-PHP core carries no `illuminate/*` requirement; the Laravel
ServiceProvider, Facade, config, models, listen command and events live in the
same package but only activate under Laravel via package auto-discovery, with
`illuminate/support` declared as `suggest`, not `require`. One repo and one
release stream; non-Laravel users simply never load the bridge classes.

## Consequences

- The bridge code must not be referenced from the core, and must degrade to a
  no-op when Laravel is absent (guarded service provider registration).
- A future split into two packages would be a namespace-breaking change, so the
  bridge namespace is kept clearly separated from day one to keep that option open.
