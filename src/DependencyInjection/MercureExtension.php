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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\AliasDeprecatedPublicServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Debug\TraceableHub;
use Symfony\Component\Mercure\Debug\TraceablePublisher;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Mercure\EventSubscriber\SetCookieSubscriber;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
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
use Symfony\Component\Mercure\Twig\MercureExtension as TwigMercureExtension;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\UX\Turbo\Bridge\Mercure\Broadcaster;
use Symfony\UX\Turbo\Bridge\Mercure\TurboStreamListenRenderer;
use Twig\Environment;

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
        $defaultHubId = null;
        $hubUrls = [];
        $traceableHubs = [];
        $hubs = [];
        $defaultHubUrl = null;
        $defaultHubName = null;
        $enableProfiler = ($config['enable_profiler'] ?? $container->getParameter('kernel.debug')) && class_exists(Stopwatch::class);
        foreach ($config['hubs'] as $name => $hub) {
            $tokenFactory = null;
            if (isset($hub['jwt'])) {
                $tokenProvider = null;
                if (isset($hub['jwt']['value'])) {
                    $tokenProvider = sprintf('mercure.hub.%s.jwt.provider', $name);

                    $container->register($tokenProvider, StaticTokenProvider::class)
                        ->addArgument($hub['jwt']['value'])
                        ->addTag('mercure.jwt.provider')
                    ;

                    // TODO: remove the following definition in 0.4
                    $jwtProvider = sprintf('mercure.hub.%s.jwt_provider', $name);
                    $jwtProviderDefinition = $container->register($jwtProvider, StaticJwtProvider::class)
                        ->addArgument($hub['jwt']['value']);

                    $this->deprecate(
                        $jwtProviderDefinition,
                        'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future, use "'.$tokenProvider.'" instead.'
                    );
                } elseif (isset($hub['jwt']['provider'])) {
                    $tokenProvider = $hub['jwt']['provider'];
                } else {
                    if (isset($hub['jwt']['factory'])) {
                        $tokenFactory = $hub['jwt']['factory'];
                    } else {
                        // 'secret' must be set.
                        $tokenFactory = sprintf('mercure.hub.%s.jwt.factory', $name);
                        $container->register($tokenFactory, LcobucciFactory::class)
                            ->addArgument($hub['jwt']['secret'])
                            ->addArgument($hub['jwt']['algorithm'])
                            ->addArgument(null)
                            ->addArgument($hub['jwt']['passphrase'])
                            ->addTag('mercure.jwt.factory')
                        ;
                    }

                    $tokenProvider = sprintf('mercure.hub.%s.jwt.provider', $name);
                    $container->register($tokenProvider, FactoryTokenProvider::class)
                        ->addArgument(new Reference($tokenFactory))
                        ->addArgument($hub['jwt']['subscribe'] ?? [])
                        ->addArgument($hub['jwt']['publish'] ?? [])
                        ->addTag('mercure.jwt.factory')
                    ;

                    $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, $name);
                    $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, "{$name}Factory");
                    $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, "{$name}TokenFactory");
                }
            } else {
                $jwtProvider = $hub['jwt_provider'];
                $tokenProvider = sprintf('mercure.hub.%s.jwt.provider', $name);

                $container->register($tokenProvider, CallableTokenProvider::class)
                    ->addArgument(new Reference($jwtProvider))
                    ->addTag('mercure.jwt.provider')
                ;
            }

            $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, $name);
            $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, "{$name}Provider");
            $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, "{$name}TokenProvider");

            $hubUrls[$name] = $hub['url'];
            $hubId = sprintf('mercure.hub.%s', $name);
            $publisherId = sprintf('mercure.hub.%s.publisher', $name);
            $hubs[$name] = new Reference($hubId);
            if (!$defaultPublisher && ($config['default_hub'] ?? $name) === $name) {
                $defaultHubName = $name;
                $defaultHubId = $hubId;
                $defaultHubUrl = $hub['url'];
                $defaultPublisher = $publisherId;
            }

            $container->register($hubId, Hub::class)
                ->addArgument($hub['url'])
                ->addArgument(new Reference($tokenProvider))
                ->addArgument($tokenFactory ? new Reference($tokenFactory) : null)
                ->addArgument($hub['public_url'])
                ->addArgument(new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
                ->addTag('mercure.hub')
            ;

            $container->registerAliasForArgument($hubId, HubInterface::class, "{$name}Hub");
            $container->registerAliasForArgument($hubId, HubInterface::class, $name);

            $publisherDefinition = $container->register($publisherId, Publisher::class)
                ->addArgument($hub['url'])
                ->addArgument(new Reference($tokenProvider))
                ->addArgument(new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
                ->addTag('mercure.publisher')
            ;

            $this->deprecate(
                $publisherDefinition,
                'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future, use "'.$hubId.'" instead.'
            );

            $this->deprecate(
                $container->registerAliasForArgument($publisherId, PublisherInterface::class, "{$name}Publisher"),
                'The "%alias_id%" service is deprecated. You should stop using it, as it will be removed in the future, use "'.$hubId.'" instead.'
            );

            $this->deprecate(
                $container->registerAliasForArgument($publisherId, PublisherInterface::class, $name),
                'The "%alias_id%" service is deprecated. You should stop using it, as it will be removed in the future, use "'.$hubId.'" instead.'
            );

            $bus = $hub['bus'] ?? null;
            $attributes = null === $bus ? [] : ['bus' => $hub['bus']];

            $messengerHandlerId = sprintf('mercure.hub.%s.message_handler', $name);
            $container->register($messengerHandlerId, UpdateHandler::class)
                ->addArgument(new Reference($hubId))
                ->addTag('messenger.message_handler', $attributes);

            if ($enableProfiler) {
                $traceablePublisher = $container->register("$publisherId.traceable", TraceablePublisher::class)
                    ->setDecoratedService($publisherId)
                    ->addArgument(new Reference("$publisherId.traceable.inner"))
                    ->addArgument(new Reference('debug.stopwatch'));

                $this->deprecate($traceablePublisher, 'The "%service_id%" service is deprecated. Use "'.$hubId.'.traceable" instead.');

                $traceableHubs[$name] = new Reference("$publisherId.traceable");

                $container->register("$hubId.traceable", TraceableHub::class)
                    ->setDecoratedService($hubId)
                    ->addArgument(new Reference("$hubId.traceable.inner"))
                    ->addArgument(new Reference('debug.stopwatch'));

                $traceableHubs[$name] = new Reference("$hubId.traceable");
            }

            if (class_exists(Broadcaster::class)) {
                $container->register("turbo.mercure.{$name}.renderer", TurboStreamListenRenderer::class)
                    ->addArgument(new Reference($hubId))
                    ->addArgument(new Reference('webpack_encore.twig_stimulus_extension'))
                    ->addArgument(new Reference('turbo.id_accessor'))
                    ->addTag('turbo.renderer.stream_listen', ['transport' => $name]);

                if ($defaultHubName === $name && 'default' !== $name) {
                    $container->getDefinition("turbo.mercure.{$name}.renderer")
                        ->addTag('turbo.renderer.stream_listen', ['transport' => 'default']);
                }

                $container->register("turbo.mercure.{$name}.broadcaster", Broadcaster::class)
                    ->addArgument($name)
                    ->addArgument(new Reference($hubId))
                    ->addTag('turbo.broadcaster');
            }
        }

        if ($enableProfiler) {
            $container->register('data_collector.mercure', MercureDataCollector::class)
                ->addArgument(new IteratorArgument($traceableHubs))
                ->addTag('data_collector', [
                    'template' => '@Mercure/Collector/mercure.html.twig',
                    'id' => 'mercure',
                ]);
        }

        $container->setAlias(HubInterface::class, $defaultHubId);

        $this->deprecate(
            $container->setAlias(Publisher::class, $defaultPublisher),
            'The "%alias_id%" service alias is deprecated. Use "'.Hub::class.'" instead.'
        );

        $this->deprecate(
            $container->setAlias(PublisherInterface::class, $defaultPublisher),
            'The "%alias_id%" service alias is deprecated. Use "'.HubInterface::class.'" instead.'
        );

        $container->register(HubRegistry::class)
            ->addArgument(new Reference($defaultHubId))
            ->addArgument($hubs)
        ;

        $container->register(Authorization::class)
            ->addArgument(new Reference(HubRegistry::class))
            ->addArgument($config['default_cookie_lifetime'])
        ;

        $container->register(Discovery::class)
            ->addArgument(new Reference(HubRegistry::class))
        ;

        if (class_exists(SetCookieSubscriber::class)) {
            $container->register(SetCookieSubscriber::class)
                ->addTag('kernel.event_subscriber', ['priority' => -10]);
        }

        if (class_exists(Environment::class) && class_exists(TwigMercureExtension::class)) {
            $container->register(TwigMercureExtension::class)
                ->setArguments([new Reference(HubRegistry::class), new Reference(Authorization::class), new Reference('request_stack')])
                ->addTag('twig.extension');
        }

        // TODO: remove these parameters in the next release.
        $container->setParameter('mercure.hubs', $hubUrls);
        $container->setParameter('mercure.default_hub', $defaultHubUrl);
    }

    /**
     * @param Definition|Alias $definition
     */
    private function deprecate($definition, string $message): void
    {
        if (class_exists(AliasDeprecatedPublicServicesPass::class)) {
            $definition->setDeprecated('symfony/mercure-bundle', '0.2', $message);
        } else {
            /* @phpstan-ignore-next-line */
            $definition->setDeprecated(true, $message);
        }
    }
}
