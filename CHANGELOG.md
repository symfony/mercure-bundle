CHANGELOG
=========

0.2.1
-----

* Fix a crash in `MercureDataCollector`

0.2.0
-----

* Compatibility with Symfony 5
* Add a profiler panel
* Autowire `Symfony\Component\Mercure\PublisherInterface` instances (using `Symfony\Component\Mercure\Publisher` for autowiring is deprecated)

0.1.2
-----

* Inject the `http_client` service when available

0.1.1
-----

* Fix a deprecation triggered by the `TreeBuilder`
