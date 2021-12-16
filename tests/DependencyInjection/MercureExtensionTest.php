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
use Symfony\Bundle\MercureBundle\DependencyInjection\MercureExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Mercure\HubRegistry;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MercureExtensionTest extends TestCase
{
    public function testExtensionMinimum(): void
    {
        $config = [
            'mercure' => [
                'hubs' => [
                    'default' => [
                        'url' => 'https://demo.mercure.rocks/hub',
                        'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU',
                    ],
                ],
            ],
        ];

        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        (new MercureExtension())->load($config, $container);

        $this->assertTrue($container->hasDefinition('mercure.hub.default')); // Hub instance
        $this->assertTrue($container->hasDefinition('mercure.hub.default.publisher')); // Publisher
        $this->assertTrue($container->hasDefinition('mercure.hub.default.jwt.provider'));
        $this->assertArrayHasKey('mercure.publisher', $container->getDefinition('mercure.hub.default.publisher')->getTags());
        $this->assertSame($config['mercure']['hubs']['default']['url'], $container->getDefinition('mercure.hub.default')->getArgument(0));
        $this->assertSame($config['mercure']['hubs']['default']['jwt'], $container->getDefinition('mercure.hub.default.jwt.provider')->getArgument(0));

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $default', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $default', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $default', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $defaultHub', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $defaultPublisher', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $defaultProvider', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $defaultTokenProvider', $container->getAliases());
        $this->assertArrayNotHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $defaultTokenFactory', $container->getAliases());
    }

    public function testExtension(): void
    {
        $config = [
            'mercure' => [
                'default_hub' => 'managed',
                'hubs' => [
                    'demo' => [
                        'url' => 'https://demo.mercure.rocks/hub',
                        'public_url' => 'https://example.com/.well-known/mercure',
                        'jwt' => [
                            'value' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU',
                        ],
                    ],
                    'managed' => [
                        'url' => 'https://demo.mercure.rocks/managed',
                        'jwt' => [
                            'secret' => '!ChangeMe!',
                            'publish' => ['*'],
                            'subscribe' => 'https://example.com/book/1.jsonld',
                        ],
                    ],
                    'managed2' => [
                        'url' => 'https://demo.mercure.rocks/managed',
                        'jwt' => [
                            'secret' => '!ChangeMe!',
                            'algorithm' => 'rsa.sha512',
                            'passphrase' => 'test',
                            'publish' => ['*'],
                            'subscribe' => 'https://example.com/book/1.jsonld',
                        ],
                    ],
                ],
            ],
        ];

        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        (new MercureExtension())->load($config, $container);

        $this->assertTrue($container->hasDefinition('mercure.hub.managed')); // Hub instance
        $this->assertTrue($container->hasDefinition('mercure.hub.managed.publisher')); // Publisher
        $this->assertTrue($container->hasDefinition('mercure.hub.managed.jwt.provider'));
        $this->assertTrue($container->hasDefinition('mercure.hub.managed.jwt.factory'));
        $this->assertArrayHasKey('mercure.publisher', $container->getDefinition('mercure.hub.managed.publisher')->getTags());
        $this->assertSame($config['mercure']['hubs']['managed']['url'], $container->getDefinition('mercure.hub.managed')->getArgument(0));
        $this->assertSame($config['mercure']['hubs']['managed']['jwt']['secret'], $container->getDefinition('mercure.hub.managed.jwt.factory')->getArgument(0));
        $this->assertSame([$config['mercure']['hubs']['managed']['jwt']['subscribe']], $container->getDefinition('mercure.hub.managed.jwt.provider')->getArgument(1));
        $this->assertSame($config['mercure']['hubs']['managed']['jwt']['publish'], $container->getDefinition('mercure.hub.managed.jwt.provider')->getArgument(2));

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $managed', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $managed', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $managed', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $managed', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $managedHub', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $managedPublisher', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $managedProvider', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $managedFactory', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $managedTokenProvider', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $managedTokenFactory', $container->getAliases());

        $this->assertTrue($container->hasDefinition('mercure.hub.managed2')); // Hub instance
        $this->assertTrue($container->hasDefinition('mercure.hub.managed2.publisher')); // Publisher
        $this->assertTrue($container->hasDefinition('mercure.hub.managed2.jwt.provider'));
        $this->assertTrue($container->hasDefinition('mercure.hub.managed2.jwt.factory'));
        $this->assertArrayHasKey('mercure.publisher', $container->getDefinition('mercure.hub.managed2.publisher')->getTags());
        $this->assertSame($config['mercure']['hubs']['managed2']['url'], $container->getDefinition('mercure.hub.managed2')->getArgument(0));
        $this->assertSame($config['mercure']['hubs']['managed2']['jwt']['secret'], $container->getDefinition('mercure.hub.managed2.jwt.factory')->getArgument(0));
        $this->assertSame($config['mercure']['hubs']['managed2']['jwt']['algorithm'], $container->getDefinition('mercure.hub.managed2.jwt.factory')->getArgument(1));
        $this->assertSame($config['mercure']['hubs']['managed2']['jwt']['passphrase'], $container->getDefinition('mercure.hub.managed2.jwt.factory')->getArgument(3));
        $this->assertSame([$config['mercure']['hubs']['managed2']['jwt']['subscribe']], $container->getDefinition('mercure.hub.managed2.jwt.provider')->getArgument(1));
        $this->assertSame($config['mercure']['hubs']['managed2']['jwt']['publish'], $container->getDefinition('mercure.hub.managed2.jwt.provider')->getArgument(2));

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $managed2', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $managed2', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $managed2', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $managed2', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $managed2Hub', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $managed2Publisher', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $managed2Provider', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $managed2Factory', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $managed2TokenProvider', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $managed2TokenFactory', $container->getAliases());

        $this->assertTrue($container->hasDefinition('mercure.hub.demo')); // Hub instance
        $this->assertTrue($container->hasDefinition('mercure.hub.demo.publisher')); // Publisher
        $this->assertTrue($container->hasDefinition('mercure.hub.demo.jwt.provider'));
        $this->assertFalse($container->hasDefinition('mercure.hub.demo.jwt.factory'));
        $this->assertArrayHasKey('mercure.publisher', $container->getDefinition('mercure.hub.demo.publisher')->getTags());
        $this->assertSame($config['mercure']['hubs']['demo']['url'], $container->getDefinition('mercure.hub.demo')->getArgument(0));
        $this->assertSame($config['mercure']['hubs']['demo']['public_url'], $container->getDefinition('mercure.hub.demo')->getArgument(3));
        $this->assertSame($config['mercure']['hubs']['demo']['jwt']['value'], $container->getDefinition('mercure.hub.demo.jwt.provider')->getArgument(0));

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $demo', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $demo', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $demo', $container->getAliases());
        $this->assertArrayNotHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $demo', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\HubInterface $demoHub', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $demoPublisher', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $demoProvider', $container->getAliases());
        $this->assertArrayNotHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $demoFactory', $container->getAliases());

        $this->assertArrayHasKey('Symfony\Component\Mercure\Jwt\TokenProviderInterface $demoTokenProvider', $container->getAliases());
        $this->assertArrayNotHasKey('Symfony\Component\Mercure\Jwt\TokenFactoryInterface $demoTokenFactory', $container->getAliases());
    }

    /**
     * @group legacy
     */
    public function testExtensionLegacy()
    {
        $config = [
            'mercure' => [
                'hubs' => [
                    [
                        'name' => 'default',
                        'url' => 'https://demo.mercure.rocks/hub',
                        'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU',
                    ],
                    [
                        'name' => 'managed',
                        'url' => 'https://demo.mercure.rocks/managed',
                        'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU',
                    ],
                ],
            ],
        ];

        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));
        (new MercureExtension())->load($config, $container);

        $this->assertTrue($container->hasDefinition('mercure.hub.default.jwt_provider'));
        $this->assertTrue($container->hasDefinition('mercure.hub.default.publisher'));
        $this->assertSame('https://demo.mercure.rocks/hub', $container->getDefinition('mercure.hub.default')->getArgument(0));
        $this->assertArrayHasKey('mercure.publisher', $container->getDefinition('mercure.hub.default.publisher')->getTags());
        $this->assertSame('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU', $container->getDefinition('mercure.hub.default.jwt_provider')->getArgument(0));
        $this->assertSame(['default' => 'https://demo.mercure.rocks/hub', 'managed' => 'https://demo.mercure.rocks/managed'], $container->getParameter('mercure.hubs'));
        $this->assertSame('https://demo.mercure.rocks/hub', $container->getParameter('mercure.default_hub'));
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $defaultPublisher', $container->getAliases());
        $this->assertArrayHasKey('Symfony\Component\Mercure\PublisherInterface $managedPublisher', $container->getAliases());

        $container->getDefinition(HubRegistry::class)->setPublic(true);
        $container->compile();

        $registry = $container->get(HubRegistry::class);

        $this->assertSame($config['mercure']['hubs'][0]['url'], $registry->getHub()->getUrl());
        $this->assertSame($config['mercure']['hubs'][0]['url'], $registry->getHub('default')->getUrl());
        $this->assertSame($config['mercure']['hubs'][1]['url'], $registry->getHub('managed')->getUrl());
    }
}
