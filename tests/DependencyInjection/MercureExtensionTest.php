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

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class MercureExtensionTest extends TestCase
{
    public function testExtension()
    {
        $config = [
            'mercure' => [
                'hubs' => [
                    [
                        'name' => 'default',
                        'url' => 'https://demo.mercure.rocks/hub',
                        'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU',
                    ],
                ],
            ],
        ];

        $container = new ContainerBuilder();
        (new MercureExtension())->load($config, $container);

        $this->assertTrue($container->hasDefinition('mercure.hub.default.jwt_provider'));
        $this->assertTrue($container->hasDefinition('mercure.hub.default.publisher'));
        $this->assertSame('https://demo.mercure.rocks/hub', $container->getDefinition('mercure.hub.default.publisher')->getArgument(0));
        $this->assertSame('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.e30.HB0k08BaV8KlLZ3EafCRlTDGbkd9qdznCzJQ_l8ELTU', $container->getDefinition('mercure.hub.default.jwt_provider')->getArgument(0));
        $this->assertSame(['default' => 'https://demo.mercure.rocks/hub'], $container->getParameter('mercure.hubs'));
        $this->assertSame('https://demo.mercure.rocks/hub', $container->getParameter('mercure.default_hub'));
    }
}
