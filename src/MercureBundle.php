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

namespace Symfony\Bundle\MercureBundle;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Mercure\Authorization;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class MercureBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class() implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $definition = $container->getDefinition(Authorization::class);
                if (
                    null === $definition->getArgument(1) &&
                    $container->hasParameter('session.storage.options')
                ) {
                    $definition->setArgument(
                        2,
                        $container->getParameter('session.storage.options')['cookie_lifetime'] ?? null
                    );
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);
    }
}
