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
use Symfony\Bundle\MercureBundle\DependencyInjection\MercureExtension;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Mercure\Authorization;

class MercureBundleTest extends TestCase
{
    public function testBuildSetsAuthorizationCookieLifetime(): void
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

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'session.storage.options' => ['cookie_lifetime' => 60],
        ]));

        (new MercureExtension())->load($config, $container);
        (new MercureBundle())->build($container);

        // prevent unused services removal/inlining and missing optional services errors
        $container->getCompilerPassConfig()->setRemovingPasses(array_filter($container->getCompilerPassConfig()->getRemovingPasses(), function (CompilerPassInterface $pass) {
            return !(
                $pass instanceof RemoveUnusedDefinitionsPass ||
                $pass instanceof CheckExceptionOnInvalidReferenceBehaviorPass ||
                $pass instanceof InlineServiceDefinitionsPass
            );
        }));

        $container->compile();

        $this->assertSame(60, $container->getDefinition(Authorization::class)->getArgument(1));
    }

    public function testBuildSkipsSettingAuthorizationCookieLifetimeIfNotWired(): void
    {
        $config = ['mercure' => ['hubs' => []]];

        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
        ]));

        (new MercureExtension())->load($config, $container);
        (new MercureBundle())->build($container);

        // prevent unused services removal/inlining and missing optional services errors
        $container->getCompilerPassConfig()->setRemovingPasses(array_filter($container->getCompilerPassConfig()->getRemovingPasses(), function (CompilerPassInterface $pass) {
            return !(
                $pass instanceof RemoveUnusedDefinitionsPass ||
                $pass instanceof CheckExceptionOnInvalidReferenceBehaviorPass ||
                $pass instanceof InlineServiceDefinitionsPass
            );
        }));

        $container->compile();

        $this->assertFalse($container->hasDefinition(Authorization::class));
    }
}
