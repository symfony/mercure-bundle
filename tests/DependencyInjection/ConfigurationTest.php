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

namespace Symfony\Bundle\MercureBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function testProtocolVersionDefaultsToLegacyValue()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                ],
            ],
        ]);

        $this->assertSame('0.x', $config['hubs']['default']['protocol_version']);
        $this->assertNull($config['hubs']['default']['cookie_name']);
    }

    public function testProtocolVersionAcceptsExplicit10()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                    'protocol_version' => '1.0',
                ],
            ],
        ]);

        $this->assertSame('1.0', $config['hubs']['default']['protocol_version']);
    }

    public function testProtocolVersionRejectsInvalidValue()
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                    'protocol_version' => '2.0',
                ],
            ],
        ]);
    }

    public function testCookieNameAcceptsExplicitValue()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                    'cookie_name' => 'custom_cookie',
                ],
            ],
        ]);

        $this->assertSame('custom_cookie', $config['hubs']['default']['cookie_name']);
    }

    public function testJwtClaimsAreAcceptedAndPassedThrough()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => ['secret' => '!ChangeMe!', 'claims' => ['iss' => 'https://example.com', 'sub' => 'https://example.com']],
                    'protocol_version' => '1.0',
                ],
            ],
        ]);

        $this->assertSame(['iss' => 'https://example.com', 'sub' => 'https://example.com'], $config['hubs']['default']['jwt']['claims']);
    }

    public function testJwksUriAcceptedWithProtocolVersion10()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => ['jwks_uri' => 'https://example.com/jwks.json', 'key_id' => 'key1'],
                    'protocol_version' => '1.0',
                ],
            ],
        ]);

        $this->assertSame('https://example.com/jwks.json', $config['hubs']['default']['jwt']['jwks_uri']);
        $this->assertSame('key1', $config['hubs']['default']['jwt']['key_id']);
    }

    public function testJwksUriRejectedWithoutProtocolVersion10()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/jwt\.jwks_uri.*protocol_version: 1\.0/');

        $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => ['jwks_uri' => 'https://example.com/jwks.json'],
                ],
            ],
        ]);
    }

    public function testJwksUriAndSecretAreMutuallyExclusive()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/jwt\.secret.*jwt\.jwks_uri/');

        $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => ['secret' => '!ChangeMe!', 'jwks_uri' => 'https://example.com/jwks.json'],
                    'protocol_version' => '1.0',
                ],
            ],
        ]);
    }
}
