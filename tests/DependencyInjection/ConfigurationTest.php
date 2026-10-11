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
use Symfony\Component\Mercure\ProtocolVersion;

final class ConfigurationTest extends TestCase
{
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    public function testUrlAndPublicUrlDefaultToNull()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                ],
            ],
        ]);

        $this->assertNull($config['hubs']['default']['public_url']);

        $config = $this->process([
            'hubs' => [
                'default' => [
                    'public_url' => 'https://demo.mercure.rocks/hub',
                ],
            ],
        ]);

        // no "url", and therefore no "jwt" required: HubFactory reads that as the built-in hub
        $this->assertNull($config['hubs']['default']['url']);
    }

    public function testProtocolVersionDefaultsToV1Value()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                ],
            ],
        ]);

        $this->assertSame(ProtocolVersion::V1, $config['hubs']['default']['protocol_version']);
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

        $this->assertSame(ProtocolVersion::V1, $config['hubs']['default']['protocol_version']);
    }

    public function testProtocolVersionAcceptsEnumCase()
    {
        $config = $this->process([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'jwt' => 'foo.bar.baz',
                    'protocol_version' => ProtocolVersion::Legacy,
                ],
            ],
        ]);

        $this->assertSame(ProtocolVersion::Legacy, $config['hubs']['default']['protocol_version']);
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

    /**
     * @dataProvider provideInvalidHubs
     */
    public function testInvalidHub(array $hub, string $path, string $message)
    {
        $this->assertInvalid(['hubs' => ['default' => $hub]], $path, $message);
    }

    public static function provideInvalidHubs(): iterable
    {
        $url = 'https://demo.mercure.rocks/hub';
        $jwks = 'https://example.com/jwks.json';

        yield 'no publisher token on a remote hub' => [['url' => $url], 'mercure.hubs.default', 'You must specify at least one of "publisher", "jwt", and "jwt_provider".'];
        yield '"jwt" with "publisher"' => [['url' => $url, 'jwt' => '!ChangeMe!', 'publisher' => ['value' => '!ChangeMe!']], 'mercure.hubs.default', '"jwt"/"jwt_provider" and "publisher"/"subscriber" cannot be used together.'];
        yield '"jwt" with "subscriber"' => [['url' => $url, 'jwt' => '!ChangeMe!', 'subscriber' => ['secret' => '!ChangeMe!']], 'mercure.hubs.default', '"jwt"/"jwt_provider" and "publisher"/"subscriber" cannot be used together.'];

        yield 'nothing to sign with in "jwt"' => [['url' => $url, 'jwt' => ['publish' => ['*']]], 'mercure.hubs.default.jwt', 'You must specify at least one of "jwt.value", "jwt.provider", "jwt.factory", "jwt.secret", and "jwt.jwks_uri".'];
        yield '"jwt.value" with "jwt.provider"' => [['url' => $url, 'jwt' => ['value' => '!ChangeMe!', 'provider' => 'app.provider']], 'mercure.hubs.default.jwt', '"jwt.value" and "jwt.provider" cannot be used together.'];
        yield '"jwt.secret" with "jwt.jwks_uri"' => [['url' => $url, 'jwt' => ['secret' => '!ChangeMe!', 'jwks_uri' => $jwks]], 'mercure.hubs.default.jwt', '"jwt.secret" and "jwt.jwks_uri" cannot be used together.'];
        yield '"jwt.jwks_uri" on a 0.x hub' => [['url' => $url, 'jwt' => ['jwks_uri' => $jwks], 'protocol_version' => '0.x'], 'mercure.hubs.default', '"jwt.jwks_uri" requires "protocol_version: 1.0", as it is only supported by WebTokenFactory.'];

        yield 'nothing to sign with in "publisher"' => [['url' => $url, 'publisher' => ['topics' => ['*']]], 'mercure.hubs.default.publisher', 'You must specify at least one of "publisher.value", "publisher.provider", "publisher.factory", "publisher.secret", and "publisher.jwks_uri".'];
        yield '"publisher.value" with "publisher.provider"' => [['url' => $url, 'publisher' => ['value' => '!ChangeMe!', 'provider' => 'app.provider']], 'mercure.hubs.default.publisher', '"publisher.value" and "publisher.provider" cannot be used together.'];
        yield '"publisher.secret" with "publisher.jwks_uri"' => [['url' => $url, 'publisher' => ['secret' => '!ChangeMe!', 'jwks_uri' => $jwks]], 'mercure.hubs.default.publisher', '"publisher.secret" and "publisher.jwks_uri" cannot be used together.'];
        yield '"publisher.jwks_uri" on a 0.x hub' => [['url' => $url, 'publisher' => ['jwks_uri' => $jwks], 'protocol_version' => '0.x'], 'mercure.hubs.default', '"publisher.jwks_uri" requires "protocol_version: 1.0", as it is only supported by WebTokenFactory.'];

        yield 'nothing to sign with in "subscriber"' => [['subscriber' => ['passphrase' => 'foo']], 'mercure.hubs.default.subscriber', 'You must specify at least one of "subscriber.factory", "subscriber.secret", and "subscriber.jwks_uri".'];
        yield '"subscriber.secret" with "subscriber.jwks_uri"' => [['subscriber' => ['secret' => '!ChangeMe!', 'jwks_uri' => $jwks]], 'mercure.hubs.default.subscriber', '"subscriber.secret" and "subscriber.jwks_uri" cannot be used together.'];
        yield '"subscriber.jwks_uri" on a 0.x hub' => [['subscriber' => ['jwks_uri' => $jwks], 'protocol_version' => '0.x'], 'mercure.hubs.default', '"subscriber.jwks_uri" requires "protocol_version: 1.0", as it is only supported by WebTokenFactory.'];
    }

    /**
     * @group legacy
     */
    public function testJwtAndJwtProviderAreMutuallyExclusive()
    {
        $this->assertInvalid(['hubs' => ['default' => ['url' => 'https://demo.mercure.rocks/hub', 'jwt' => '!ChangeMe!', 'jwt_provider' => 'app.provider']]], 'mercure.hubs.default', '"jwt" and "jwt_provider" cannot be used together.');
    }

    private function assertInvalid(array $config, string $path, string $message): void
    {
        try {
            $this->process($config);
        } catch (InvalidConfigurationException $e) {
            $this->assertSame(\sprintf('Invalid configuration for path "%s": %s', $path, $message), $e->getMessage());

            return;
        }

        $this->fail('The configuration is valid.');
    }
}
