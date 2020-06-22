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

namespace Symfony\Bundle\MercureBundle\DependencyInjection;

use Symfony\Bundle\MercureBundle\DataCollector\MercureDataCollector;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\AliasDeprecatedPublicServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Mercure\Debug\TraceablePublisher;
use Symfony\Component\Mercure\Jwt\StaticJwtProvider;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class MercureExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        if (!$configuration instanceof ConfigurationInterface) {
            return;
        }

        $config = $this->processConfiguration($configuration, $configs);
        if (!$config['hubs']) {
            return;
        }

        $defaultHub = $config['default_hub'] ?? null;
        $hubUrls = [];
        $publishers = [];
        $defaultHubUrl = null;
        $enableProfiler = $config['enable_profiler'] && class_exists(Stopwatch::class);
        foreach ($config['hubs'] as $name => $hub) {
            if (isset($hub['jwt'])) {
                $jwtProvider = sprintf('mercure.hub.%s.jwt_provider', $name);
                $container->register($jwtProvider, StaticJwtProvider::class)->addArgument($hub['jwt']);
            } else {
                $jwtProvider = $hub['jwt_provider'];
            }

            $hubUrls[$name] = $hub['url'];
            $hubId = sprintf('mercure.hub.%s.publisher', $name);
            if (!$defaultHub) {
                $defaultHubUrl = $hub['url'];
                $defaultHub = $hubId;
            }

            $publisherDefinition = $container->register($hubId, Publisher::class)
                ->addArgument($hub['url'])
                ->addArgument(new Reference($jwtProvider))
                ->addArgument(new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE));

            $bus = $hub['bus'] ?? null;
            $attributes = null === $bus ? [] : ['bus' => $hub['bus']];
            $publisherDefinition->addTag('messenger.message_handler', $attributes);

            if ($enableProfiler) {
                $container->register("$hubId.traceable", TraceablePublisher::class)
                    ->setDecoratedService($hubId)
                    ->addArgument(new Reference("$hubId.traceable.inner"))
                    ->addArgument(new Reference('debug.stopwatch'));

                $publishers[$name] = new Reference("$hubId.traceable");
            }
        }

        if ($enableProfiler) {
            $container->register('data_collector.mercure', MercureDataCollector::class)
                ->addArgument(new IteratorArgument($publishers))
                ->addTag('data_collector', [
                    'template' => '@Mercure/Collector/mercure.html.twig',
                    'id' => 'mercure',
                ]);
        }

        $alias = $container->setAlias(Publisher::class, $defaultHub);

        // Use the 5.1 signature for Alias::setDeprecated()
        if (class_exists(AliasDeprecatedPublicServicesPass::class)) {
            $alias->setDeprecated('symfony/mercure-bundle', '0.2', 'The "%alias_id%" service alias is deprecated. Use "'.PublisherInterface::class.'" instead.');
        } else {
            $alias->setDeprecated(true, 'The "%alias_id%" service alias is deprecated. Use "'.PublisherInterface::class.'" instead.');
        }

        $container->setAlias(PublisherInterface::class, $defaultHub);
        $container->setParameter('mercure.hubs', $hubUrls);
        $container->setParameter('mercure.default_hub', $defaultHubUrl);
    }
}
