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

namespace Symfony\Bundle\MercureBundle\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\Test\MercureAssertionsTrait;
use Symfony\Bundle\MercureBundle\Test\UpdateRecorder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mercure\Update;

final class MercureAssertionsTraitTest extends TestCase
{
    use MercureAssertionsTrait;

    private static ?Container $container = null;

    protected function setUp(): void
    {
        $recorder = new UpdateRecorder(['default', 'other']);
        $recorder->record('default', new Update('https://example.com/books/1', '{"title":"Dune"}'));
        $recorder->record('other', new Update(['https://example.com/books/2', 'https://example.com/authors/1']));

        self::$container = new Container();
        self::$container->set('mercure.test.update_recorder', $recorder);
    }

    protected function tearDown(): void
    {
        self::$container = null;
    }

    protected static function getContainer(): ContainerInterface
    {
        return self::$container;
    }

    public function testAssertMercureUpdateCount()
    {
        self::assertMercureUpdateCount(2);
        self::assertMercureUpdateCount(1, 'default');
        self::assertMercureUpdateCount(1, 'other');

        $this->expectException(AssertionFailedError::class);
        self::assertMercureUpdateCount(2, 'default');
    }

    public function testAssertMercureUpdatePublished()
    {
        self::assertMercureUpdatePublished('https://example.com/books/1');
        self::assertMercureUpdatePublished('https://example.com/authors/1', 'other');

        $this->expectException(AssertionFailedError::class);
        self::assertMercureUpdatePublished('https://example.com/books/1', 'other');
    }

    public function testGetMercureUpdate()
    {
        $this->assertSame('{"title":"Dune"}', self::getMercureUpdate()->getData());
        $this->assertSame(['https://example.com/books/2', 'https://example.com/authors/1'], self::getMercureUpdate(1)->getTopics());
        $this->assertSame(['https://example.com/books/2', 'https://example.com/authors/1'], self::getMercureUpdate(0, 'other')->getTopics());
        $this->assertNull(self::getMercureUpdate(2));
        $this->assertNull(self::getMercureUpdate(-1));
    }

    public function testUnknownHubThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "unknown" Mercure hub does not record its updates: it is not defined or its "record_updates_in_test" option is disabled. Recording hubs: "default", "other".');

        self::getMercureUpdates('unknown');
    }

    public function testFailsWithoutRecorder()
    {
        self::$container = new Container();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Mercure updates are only recorded when "framework.test" is enabled');

        self::getMercureUpdates();
    }

    public function testResetForgetsTheUpdates()
    {
        self::getContainer()->get('mercure.test.update_recorder')->reset();

        self::assertMercureUpdateCount(0);
    }
}
