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

namespace Symfony\Bundle\MercureBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\UX\Turbo\Bridge\Mercure\Broadcaster;

/**
 * Registers a dynamic alias to use a service from WebpackEncoreBundle or StimulusBundle.
 *
 * Depending on the version of symfony/ux-turbo installed, one of these bundles
 * will be available.
 */
final class StimulusHelperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(Broadcaster::class)) {
            return;
        }

        if ($container->hasDefinition('webpack_encore.twig_stimulus_extension')) {
            $id = 'webpack_encore.twig_stimulus_extension';
        } else {
            $id = 'stimulus.helper';
        }
        $container->setAlias('turbo.mercure.stimulus_helper', new Alias($id));
    }
}
