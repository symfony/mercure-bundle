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

namespace Symfony\Bundle\MercureBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\HubFactory;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

final class HubFactoryTest extends TestCase
{
    public function testUrlSelectsARemoteHub()
    {
        $hub = HubFactory::create(
            'https://demo.mercure.rocks/hub',
            new StaticTokenProvider('foo.bar.baz'),
            null,
            'https://example.com/.well-known/mercure',
            null,
            'custom_cookie',
            ProtocolVersion::V1,
        );

        $this->assertInstanceOf(Hub::class, $hub);
        $this->assertSame('https://demo.mercure.rocks/hub', $hub->getUrl());
        $this->assertSame('https://example.com/.well-known/mercure', $hub->getPublicUrl());
        $this->assertSame('custom_cookie', $hub->getCookieName());
        $this->assertSame(ProtocolVersion::V1, $hub->getProtocolVersion());
    }

    public function testUrlWithoutATokenProviderThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs a JSON Web Token to publish with');

        HubFactory::create('https://demo.mercure.rocks/hub', null, null, null, null, null, ProtocolVersion::Legacy);
    }

    /**
     * The case behind #120: MERCURE_URL is defined but empty, which Docker Compose has no way
     * to tell apart from unset, so an empty string has to select the built-in hub too.
     *
     * @dataProvider provideEmptyUrls
     */
    public function testAnEmptyUrlSelectsTheBuiltinHub(?string $url)
    {
        $hub = HubFactory::create($url, null, null, 'https://example.com/.well-known/mercure', null, null, ProtocolVersion::Legacy);

        $this->assertInstanceOf(FrankenPhpHub::class, $hub);
        $this->assertSame('https://example.com/.well-known/mercure', $hub->getPublicUrl());
    }

    public static function provideEmptyUrls(): iterable
    {
        yield 'unset' => [null];
        yield 'empty environment variable' => [''];
    }

    /**
     * Creating the built-in hub must not need FrankenPHP: HubRegistry creates every hub, also
     * under the CLI (e.g. for the Twig extension during cache:warmup). Only publishing does.
     */
    public function testTheBuiltinHubOnlyNeedsFrankenPhpToPublish()
    {
        if (\function_exists('mercure_publish')) {
            $this->markTestSkipped('FrankenPHP\'s mercure_publish() function is available.');
        }

        $hub = HubFactory::create(null, null, null, 'https://example.com/.well-known/mercure', null, null, ProtocolVersion::Legacy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The mercure_publish() function is not available');

        $hub->publish(new Update('https://example.com/books/1', 'data'));
    }

    public function testTheBuiltinHubWithoutAPublicUrlThrows()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('set the "public_url" option');

        HubFactory::create(null, null, null, null, null, null, ProtocolVersion::Legacy);
    }
}
