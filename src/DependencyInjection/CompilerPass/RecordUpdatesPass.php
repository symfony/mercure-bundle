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

use Symfony\Bundle\MercureBundle\Test\RecordingHub;
use Symfony\Bundle\MercureBundle\Test\UpdateRecorder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Records the updates instead of publishing them when "framework.test" is enabled.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 *
 * @internal
 */
final class RecordUpdatesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // registered by FrameworkBundle when "framework.test" is enabled
        if (!$container->hasDefinition('test.service_container')) {
            return;
        }

        $hubs = [];
        foreach ($container->findTaggedServiceIds('mercure.hub') as $hubId => $tags) {
            if (!str_starts_with($hubId, 'mercure.hub.')) {
                continue;
            }

            $name = substr($hubId, \strlen('mercure.hub.'));
            $hubs[] = $name;

            $container->register("$hubId.recording", RecordingHub::class)
                // before the profiler's TraceableHub (priority 0), so that it still lists the updates
                ->setDecoratedService($hubId, null, 1)
                ->addArgument(new Reference("$hubId.recording.inner"))
                ->addArgument(new Reference('mercure.test.update_recorder'))
                ->addArgument($name);
        }

        if (!$hubs) {
            return;
        }

        $container->register('mercure.test.update_recorder', UpdateRecorder::class)
            // public, as the private services of the test container are only available once instantiated
            ->setPublic(true)
            ->addArgument($hubs)
            ->addTag('kernel.reset', ['method' => 'reset']);
    }
}
