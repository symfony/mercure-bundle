CHANGELOG
=========

Unreleased
----------

* **BC break:** Stop registering the legacy `Publisher` / `PublisherInterface` / `TraceablePublisher` services and aliases, and the `StaticJwtProvider` service (`mercure.hub.*.jwt_provider`). Apps must use `Hub` / `HubInterface` / `TraceableHub` and `StaticTokenProvider` (`mercure.hub.*.jwt.provider`) instead. This removes the `symfony/mercure` 0.5 deprecations that were triggered on every container boot even when the Hub API was used exclusively (#121)

0.5.0
-----

* Add support for the Mercure protocol 1.0, alongside the existing 0.x protocol: new `protocol_version` (`0.x`, default, or `1.0`) and `cookie_name` per-hub options
* The `jwt.secret` convenience option now uses `LcobucciFactory`'s Mercure protocol 1.0 support on a `protocol_version: 1.0` hub
* Add `jwt.jwks_uri` and `jwt.key_id`, fetching the signing key from a JSON Web Key Set endpoint via `WebTokenFactory` instead of `jwt.secret` (requires `web-token/jwt-library`, Mercure 1.0 hubs only)
* `jwt.algorithm` no longer has a single default, as the two token factories name algorithms differently: it defaults to `hmac.sha256` with `jwt.secret` (`LcobucciFactory`) and to the JWA name `HS256` with `jwt.jwks_uri` (`WebTokenFactory`, which also accepts `PS256`/`PS384`/`PS512` and `EdDSA`)
* Add `jwt.claims` (e.g. `iss`/`sub`/`client_id`), required by RFC 9068 when using `jwt.secret`/`jwt.jwks_uri` on a `protocol_version: 1.0` hub; `aud` defaults to the hub's own URL when not set. Missing required claims fail at container compile time, and a `0.x` hub without `jwt.claims` keeps minting byte-identical legacy tokens (no `DefaultClaimsTokenFactory` wrapping, no automatic `aud`)
* Require `symfony/mercure` `^0.8`
* Follow `symfony/mercure`'s `Hub`/`FrankenPhpHub` constructor parameter reorder (`$cookieName` before `$protocolVersion`) and its `Grant`-based `TokenFactoryInterface::create()`; `jwt.subscribe`/`jwt.publish` are now wired in as one `publish` and one `subscribe` `Grant`, always both present so the legacy claim's shape is unchanged for hubs configuring neither
* Require PHP 8.2, up from 8.1, following `symfony/mercure`

0.4.3
-----

* Extend the `Extension` class from the `DependencyInjection` component
* Bound the `symfony/mercure` dependency to supported versions
* Fix `branch-alias`

0.4.2
-----

* Fix Twig extension registration when using Symfony Mercure 0.7 (again)

0.4.1
-----

* Fix Twig extension registration when using Symfony Mercure 0.7

0.4.0
-----

* Add support for FrankenPHP's `mercure_publish()` function
* Allow Symfony 8
* Compatibility with PHP 8.5
* Pass Twig as a dependency of `TurboStreamListenRenderer`
* Fix cache warmup without JWT secrets
* Drop support for unmaintained PHP and Symfony versions

0.3.9
-----

* Explicitly mark method parameters as nullable
* Adapt icon to profiler redesign

0.3.8
-----

* Allow Symfony 7

0.3.7
-----

* Support for `symfony/ux-turbo` 2.9 using `symfony/stimulus-bundle`

0.3.6
-----

* Compatibility with `lcobucci/jwt` 5.0

0.3.5
-----

* Use the right argument index for `Authorization` cookie lifetime

0.3.4
-----

* Add support for JWT signers requiring a passphrase
* Throw an exception if neither the `jwt` nor the `jwt_provider` configuration key is defined.

0.3.3
-----

* Deprecate `enable_profiler` configuration
* Add support for Mercure Component 0.6
* Compatibility with Symfony 6.0

0.3.2
-----

* Full compatibility with PHP 7.1
* Enable JWT support by default

0.3.1
-----

* Add a configuration option to set a default expiration for the JWT and the cookie when using the `Authorization` class

0.3.0
-----

* Upgrade to `symfony/mercure` 0.5
* Add integration with `symfony/ux-turbo`
* Register autowiring aliases for hubs
* Add `mercure.publisher` tag on publisher services

0.2.6
-----

* Expose privateness of published messages in profiler panel
* Compatibility with PHP 8

0.2.5
-----

* Fix a bug in the debugger panel
* Compatibility with Symfony 5.1

0.2.4
-----

* Compatibility with Mercure 0.10

0.2.3
-----

* Fix a bug preventing the profiler to work

0.2.2
-----

* Fix compatibility with Symfony 5

0.2.1
-----

* Fix a crash in `MercureDataCollector`

0.2.0
-----

* Fix compatibility with Symfony 5 beta
* Add a profiler panel
* Autowire `Symfony\Component\Mercure\PublisherInterface` instances (using `Symfony\Component\Mercure\Publisher` for autowiring is deprecated)

0.1.2
-----

* Inject the `http_client` service when available

0.1.1
-----

* Fix a deprecation triggered by the `TreeBuilder`
