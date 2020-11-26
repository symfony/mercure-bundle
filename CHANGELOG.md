CHANGELOG
=========

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
