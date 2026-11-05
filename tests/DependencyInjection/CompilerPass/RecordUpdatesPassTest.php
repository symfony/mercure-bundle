<?php

/*
 * This file is part of the Mercure Component project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Bundle\MercureBundle\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\DependencyInjection\CompilerPass\RecordUpdatesPass;
use Symfony\Bundle\MercureBundle\DependencyInjection\MercureExtension;
use Symfony\Bundle\MercureBundle\Test\RecordingHub;
use Symfony\Bundle\MercureBundle\Test\UpdateRecorder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mercure\Debug\TraceableHub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\RemoteHubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Stopwatch\Stopwatch;

final class RecordUpdatesPassTest extends TestCase
{
    public function testHubsAreLeftAloneWithoutFrameworkTest()
    {
        $container = $this->createContainer(false, false);
        $container->compile();

        $this->assertFalse($container->has('mercure.test.update_recorder'));
        $this->assertNotInstanceOf(RecordingHub::class, $container->get('mercure.hub.default'));
    }

    public function testUpdatesAreRecordedInsteadOfPublished()
    {
        $container = $this->createContainer(true, false);
        $container->compile();

        $hub = $container->get('mercure.hub.default');
        $this->assertInstanceOf(RecordingHub::class, $hub);
        $this->assertInstanceOf(RemoteHubInterface::class, $container->get(RemoteHubInterface::class));
        $this->assertSame('https://demo.mercure.rocks/hub', $hub->getUrl());

        $this->assertSame('urn:uuid:custom', $hub->publish(new Update('https://example.com/books/1', '{}', id: 'urn:uuid:custom')));
        $this->assertMatchesRegularExpression('/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $container->get('mercure.hub.other')->publish(new Update('https://example.com/books/2')));

        $recorder = $container->get('mercure.test.update_recorder');
        $this->assertInstanceOf(UpdateRecorder::class, $recorder);
        $this->assertSame(['https://example.com/books/1', 'https://example.com/books/2'], array_map(static fn (Update $update): string => $update->getTopics()[0], $recorder->getUpdates()));
        $this->assertSame(['https://example.com/books/2'], $recorder->getUpdates('other')[0]->getTopics());
        $this->assertSame([['method' => 'reset']], $container->findDefinition('mercure.test.update_recorder')->getTag('kernel.reset'));
    }

    public function testTheProfilerStillListsTheRecordedUpdates()
    {
        $container = $this->createContainer(true, true);
        $container->compile();

        $hub = $container->get('mercure.hub.default');
        $this->assertInstanceOf(TraceableHub::class, $hub);
        $hub->publish(new Update('https://example.com/books/1'));

        $this->assertSame(1, $hub->count());
        $this->assertCount(1, $container->get('mercure.test.update_recorder')->getUpdates('default'));
    }

    public function testHubsCanOptOut()
    {
        $container = $this->createContainer(true, false, ['record_updates_in_test' => false]);
        $container->compile();

        $this->assertInstanceOf(RecordingHub::class, $container->get('mercure.hub.default'));
        $this->assertNotInstanceOf(RecordingHub::class, $container->get('mercure.hub.other'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recording hubs: "default".');
        $container->get('mercure.test.update_recorder')->getUpdates('other');
    }

    public function testNoRecorderWhenEveryHubOptsOut()
    {
        $container = $this->createContainer(true, false, ['record_updates_in_test' => false], ['record_updates_in_test' => false]);
        $container->compile();

        $this->assertFalse($container->has('mercure.test.update_recorder'));
        $this->assertNotInstanceOf(RecordingHub::class, $container->get('mercure.hub.default'));
    }

    /**
     * @param array<string, mixed> $otherHub
     * @param array<string, mixed> $defaultHub
     */
    private function createContainer(bool $test, bool $debug, array $otherHub = [], array $defaultHub = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => $debug]));
        (new MercureExtension())->load(['mercure' => ['hubs' => [
            'default' => ['url' => 'https://demo.mercure.rocks/hub', 'jwt' => 'token'] + $defaultHub,
            'other' => ['url' => 'https://other.example.com/hub', 'jwt' => 'token'] + $otherHub,
        ]]], $container);

        // any request would fail the test: nothing must reach the hub
        $container->register('http_client', MockHttpClient::class)
            ->addArgument(static fn () => throw new \LogicException('No update must be sent to the hub.'));
        if ($debug) {
            $container->register('debug.stopwatch', Stopwatch::class);
        }
        if ($test) {
            $container->register('test.service_container', \stdClass::class);
        }

        $container->addCompilerPass(new RecordUpdatesPass());
        foreach ([HubInterface::class, RemoteHubInterface::class, 'mercure.hub.default', 'mercure.hub.other'] as $id) {
            $container->hasAlias($id) ? $container->getAlias($id)->setPublic(true) : $container->getDefinition($id)->setPublic(true);
        }

        return $container;
    }
}
