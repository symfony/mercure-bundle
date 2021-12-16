CHANGELOG
=========

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
