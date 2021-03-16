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
use Symfony\Bundle\MercureBundle\LinkHeaderUtils;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\AliasDeprecatedPublicServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Mercure\Debug\TraceablePublisher;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\CallableTokenProvider;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticJwtProvider;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Messenger\UpdateHandler;
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

        $defaultPublisher = null;
        $defaultHub = null;
        $hubUrls = [];
        $traceablePublishers = [];
        $defaultHubUrl = null;
        $defaultHubName = null;
        $enableProfiler = $config['enable_profiler'] && class_exists(Stopwatch::class);
        foreach ($config['hubs'] as $name => $hub) {
            if (isset($hub['jwt'])) {
                $tokenProvider = null;
                if (isset($hub['jwt']['value'])) {
                    $tokenProvider = sprintf('mercure.hub.%s.jwt.provider', $name);

                    $container->register($tokenProvider, StaticTokenProvider::class)->addArgument($hub['jwt']['value']);

                    // TODO: remove the following definition in 0.4
                    $jwtProvider = sprintf('mercure.hub.%s.jwt_provider', $name);
                    $jwtProviderDefinition = $container->register($jwtProvider, StaticJwtProvider::class)
                        ->addArgument($hub['jwt']['value']);
                    if (class_exists(AliasDeprecatedPublicServicesPass::class)) {
                        $jwtProviderDefinition->setDeprecated('symfony/mercure-bundle', '0.3', 'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future, use "'.$tokenProvider.'" instead.');
                    } else {
                        $jwtProviderDefinition->setDeprecated(true, 'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future, use "'.$tokenProvider.'" instead.');
                    }
                } elseif (isset($hub['jwt']['provider'])) {
                    $tokenProvider = $hub['jwt']['provider'];
                }

                if (isset($hub['jwt']['factory'])) {
                    $tokenFactory = $hub['jwt']['factory'];
                } elseif (null === $tokenProvider) {
                    // 'secret' must be set.
                    $tokenFactory = sprintf('mercure.hub.%s.jwt.factory', $name);

                    $container->register($tokenFactory, LcobucciFactory::class)->addArgument($hub['jwt']['secret']);
                }

                if (null === $tokenProvider) {
                    $tokenProvider = sprintf('mercure.hub.%s.jwt.provider', $name);

                    $container->register($tokenProvider, FactoryTokenProvider::class)
                        ->addArgument(new Reference($tokenFactory))
                        ->addArgument($hub['jwt']['publish'] ?? [])
                        ->addArgument($hub['jwt']['subscribe'] ?? []);

                    $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, $name);
                    $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, "{$name}Factory");
                    $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, "{$name}TokenFactory");
                }
            } else {
                $jwtProvider = $hub['jwt_provider'];
                $tokenProvider = sprintf('mercure.hub.%s.jwt.provider', $name);

                $container->register($tokenProvider, CallableTokenProvider::class)
                    ->addArgument(new Reference($jwtProvider));
            }

            $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, $name);
            $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, "{$name}Provider");
            $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, "{$name}TokenProvider");

            $hubUrls[$name] = $hub['url'];
            $hubId = sprintf('mercure.hub.%s', $name);
            $publisherId = sprintf('mercure.hub.%s.publisher', $name);
            if (!$defaultPublisher && $name === ($config['default_hub'] ?? $name)) {
                $defaultHub = $hubId;
                $defaultHubUrl = $hub['url'];
                $defaultPublisher = $publisherId;
            }

            $container->register($hubId, Hub::class)
                ->addArgument($hub['url'])
                ->addArgument(new Reference($tokenProvider));

            $container->registerAliasForArgument($hubId, Hub::class, "{$name}Hub");
            $container->registerAliasForArgument($hubId, Hub::class, $name);

            $container->register($publisherId, Publisher::class)
                ->addArgument(new Reference($hubId))
                ->addArgument(new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
                ->addTag('mercure.publisher')
            ;

            $bus = $hub['bus'] ?? null;
            $attributes = null === $bus ? [] : ['bus' => $hub['bus']];

            $messengerHandlerId = sprintf('mercure.hub.%s.message_handler', $name);
            $container->register($messengerHandlerId, UpdateHandler::class)
                ->addArgument(new Reference($publisherId))
                ->addTag('messenger.message_handler', $attributes);

            if ($enableProfiler) {
                $container->register("$publisherId.traceable", TraceablePublisher::class)
                    ->setDecoratedService($publisherId)
                    ->addArgument(new Reference("$publisherId.traceable.inner"))
                    ->addArgument(new Reference('debug.stopwatch'));

                $traceablePublishers[$name] = new Reference("$publisherId.traceable");
            }

            $container->registerAliasForArgument($publisherId, PublisherInterface::class, "{$name}Publisher");
            $container->registerAliasForArgument($publisherId, PublisherInterface::class, $name);
        }

        if ($enableProfiler) {
            $container->register('data_collector.mercure', MercureDataCollector::class)
                ->addArgument(new IteratorArgument($traceablePublishers))
                ->addTag('data_collector', [
                    'template' => '@Mercure/Collector/mercure.html.twig',
                    'id' => 'mercure',
                ]);
        }

        $alias = $container->setAlias(Publisher::class, $defaultPublisher);

        // Use the 5.1 signature for Alias::setDeprecated()
        if (class_exists(AliasDeprecatedPublicServicesPass::class)) {
            $alias->setDeprecated('symfony/mercure-bundle', '0.2', 'The "%alias_id%" service alias is deprecated. Use "'.PublisherInterface::class.'" instead.');
        } else {
            $alias->setDeprecated(true, 'The "%alias_id%" service alias is deprecated. Use "'.PublisherInterface::class.'" instead.');
        }

        $container->setAlias(PublisherInterface::class, $defaultPublisher);
        $container->setAlias(Hub::class, $defaultHub);

        $container->register(LinkHeaderUtils::class)
            ->addArgument('%mercure.hubs%');

        $container->setParameter('mercure.hubs', $hubUrls);
        $container->setParameter('mercure.default_hub', $defaultHubUrl);
    }
}
