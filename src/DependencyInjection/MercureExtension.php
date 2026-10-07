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

use Jose\Component\Core\JWK;
use Symfony\Bundle\MercureBundle\DataCollector\MercureDataCollector;
use Symfony\Bundle\MercureBundle\HubFactory;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Debug\TraceableHub;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Mercure\EventSubscriber\SetCookieSubscriber;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\CallableTokenProvider;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Symfony\Component\Mercure\Messenger\UpdateHandler;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\RemoteHubInterface;
use Symfony\Component\Mercure\Twig\MercureExtension as TwigMercureExtension;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\UX\Turbo\Bridge\Mercure\Broadcaster;
use Symfony\UX\Turbo\Bridge\Mercure\TurboStreamListenRenderer;
use Twig\Environment;
use Twig\Extension\AbstractExtension;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class MercureExtension extends Extension
{
    public function __construct(
        private readonly ?bool $webTokenLibraryInstalled = null,
    ) {
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);
        if (!$configuration instanceof ConfigurationInterface) {
            return;
        }

        $config = $this->processConfiguration($configuration, $configs);
        if (!$config['hubs']) {
            return;
        }

        $defaultHubId = null;
        $defaultHubIsRemote = false;
        $traceableHubs = [];
        $hubs = [];
        $defaultHubName = null;
        $debug = (bool) $container->getParameter('kernel.debug');
        $enableProfiler = ($config['enable_profiler'] ?? $debug) && class_exists(Stopwatch::class);
        foreach ($config['hubs'] as $name => $hub) {
            $remoteHub = null !== $hub['url'] && '' !== $hub['url'];
            $protocolVersion = $hub['protocol_version'];
            // browsers drop "__Secure-" cookies over plain HTTP; this is also the hub's "playground" default
            $cookieName = $hub['cookie_name'] ?? ($debug && ProtocolVersion::V1 === $protocolVersion ? 'mercure_access_token' : null);

            $tokenFactory = null;
            $tokenProvider = null;
            if (isset($hub['jwt'])) {
                if (isset($hub['jwt']['value'])) {
                    $tokenProvider = $this->registerStaticTokenProvider($container, $name, $hub['jwt']['value']);
                } elseif (isset($hub['jwt']['provider'])) {
                    $tokenProvider = $hub['jwt']['provider'];
                } else {
                    // 'factory', or 'secret', must be set.
                    $tokenFactory = $this->registerLazyTokenFactory($container, $name, \sprintf('mercure.hub.%s.jwt.factory', $name), $hub['jwt'], 'jwt', $protocolVersion, $hub);
                    $tokenProvider = $this->registerFactoryTokenProvider($container, $name, $tokenFactory, $hub['jwt']['publish'] ?? [], $hub['jwt']['subscribe'] ?? []);
                }
            } elseif (isset($hub['jwt_provider'])) {
                $jwtProvider = $hub['jwt_provider'];
                $tokenProvider = \sprintf('mercure.hub.%s.jwt.provider', $name);

                $container->register($tokenProvider, CallableTokenProvider::class)
                    ->addArgument(new Reference($jwtProvider))
                    ->addTag('mercure.jwt.provider');
            } else {
                $publisher = $hub['publisher'] ?? null;
                $subscriber = $hub['subscriber'] ?? null;
                $sharedFactory = isset($publisher, $subscriber) && !isset($publisher['factory']) && !isset($subscriber['factory']) && $this->signingOptions($publisher) === $this->signingOptions($subscriber);

                if (null !== $subscriber) {
                    $tokenFactory = $this->registerLazyTokenFactory($container, $name, \sprintf($sharedFactory ? 'mercure.hub.%s.jwt.factory' : 'mercure.hub.%s.subscriber.jwt.factory', $name), $subscriber, 'subscriber', $protocolVersion, $hub);
                }

                if (isset($publisher['value'])) {
                    $tokenProvider = $this->registerStaticTokenProvider($container, $name, $publisher['value']);
                } elseif (isset($publisher['provider'])) {
                    $tokenProvider = $publisher['provider'];
                } elseif (null !== $publisher) {
                    $publisherFactory = $sharedFactory ? $tokenFactory : $this->registerLazyTokenFactory($container, $name, \sprintf('mercure.hub.%s.publisher.jwt.factory', $name), $publisher, 'publisher', $protocolVersion, $hub);
                    $tokenProvider = $this->registerFactoryTokenProvider($container, $name, $publisherFactory, $publisher['topics'] ?? [], []);
                }
            }

            if (null !== $tokenFactory) {
                $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, $name);
                $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, "{$name}Factory");
                $container->registerAliasForArgument($tokenFactory, TokenFactoryInterface::class, "{$name}TokenFactory");
            }

            if (null !== $tokenProvider) {
                $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, $name);
                $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, "{$name}Provider");
                $container->registerAliasForArgument($tokenProvider, TokenProviderInterface::class, "{$name}TokenProvider");
            }

            $hubId = \sprintf('mercure.hub.%s', $name);
            $hubs[$name] = new Reference($hubId);
            if (null === $defaultHubId && ($config['default_hub'] ?? $name) === $name) {
                $defaultHubName = $name;
                $defaultHubId = $hubId;
                $defaultHubIsRemote = $remoteHub;
            }

            // Hub or FrankenPhpHub: the decision needs the resolved "url", so it belongs to
            // the factory, not here. Arguments mirror Hub::__construct().
            $container->register($hubId, HubInterface::class)
                ->setFactory([HubFactory::class, 'create'])
                ->addArgument($hub['url'])
                ->addArgument($tokenProvider ? new Reference($tokenProvider) : null)
                ->addArgument($tokenFactory ? new Reference($tokenFactory) : null)
                ->addArgument($hub['public_url'])
                ->addArgument(isset($hub['http_client']) ? new Reference($hub['http_client']) : new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
                ->addArgument($cookieName)
                ->addArgument($protocolVersion)
                ->addTag('mercure.hub');

            $container->registerAliasForArgument($hubId, HubInterface::class, "{$name}Hub");
            $container->registerAliasForArgument($hubId, HubInterface::class, $name);
            if ($remoteHub) {
                $container->registerAliasForArgument($hubId, RemoteHubInterface::class, "{$name}Hub");
                $container->registerAliasForArgument($hubId, RemoteHubInterface::class, $name);
            }

            $bus = $hub['bus'] ?? null;
            $attributes = null === $bus ? [] : ['bus' => $hub['bus']];

            $messengerHandlerId = \sprintf('mercure.hub.%s.message_handler', $name);
            $container->register($messengerHandlerId, UpdateHandler::class)
                ->addArgument(new Reference($hubId))
                ->addTag('messenger.message_handler', $attributes);

            if ($enableProfiler) {
                $container->register("$hubId.traceable", TraceableHub::class)
                    ->setDecoratedService($hubId)
                    ->addArgument(new Reference("$hubId.traceable.inner"))
                    ->addArgument(new Reference('debug.stopwatch'))
                    // not autoconfigured: without the tag, long-running processes (e.g. FrankenPHP's
                    // worker mode) would pile up every request's messages in the profiler
                    ->addTag('kernel.reset', ['method' => 'reset']);

                $traceableHubs[$name] = new Reference("$hubId.traceable");
            }

            if (class_exists(Broadcaster::class)) {
                $container->register("turbo.mercure.{$name}.renderer", TurboStreamListenRenderer::class)
                    ->addArgument(new Reference($hubId))
                    // uses an alias dynamically registered in a compiler pass
                    ->addArgument(new Reference('turbo.mercure.stimulus_helper'))
                    ->addArgument(new Reference('turbo.id_accessor'))
                    ->addArgument(new Reference('twig'))
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

        if (null === $defaultHubId) {
            throw new InvalidConfigurationException(\sprintf('The "mercure.default_hub" option refers to the "%s" hub, which is not defined. Defined hubs: "%s".', $config['default_hub'], implode('", "', array_keys($config['hubs']))));
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
        if ($defaultHubIsRemote) {
            $container->setAlias(RemoteHubInterface::class, $defaultHubId);
        }

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
            $definition = $container->register(TwigMercureExtension::class)
                ->setArguments([new Reference(HubRegistry::class), new Reference(Authorization::class), new Reference('request_stack')]);

            if (is_a(TwigMercureExtension::class, AbstractExtension::class, true)) {
                $definition->addTag('twig.extension');
            } else {
                $definition->addTag('twig.attribute_extension')->addTag('twig.runtime');
            }
        }
    }

    private function registerStaticTokenProvider(ContainerBuilder $container, string $name, string $token): string
    {
        $tokenProvider = \sprintf('mercure.hub.%s.jwt.provider', $name);
        $container->register($tokenProvider, StaticTokenProvider::class)
            ->addArgument($token)
            ->addTag('mercure.jwt.provider');

        return $tokenProvider;
    }

    /**
     * @param string[] $publish
     * @param string[] $subscribe
     */
    private function registerFactoryTokenProvider(ContainerBuilder $container, string $name, string $tokenFactory, array $publish, array $subscribe): string
    {
        // Always grant both actions, even over an empty topic list: this preserves the
        // legacy claim's exact historical shape (a "mercure" object with "publish"/"subscribe"
        // keys, always present). Under protocol 1.0 an empty-topics grant is inert either way.
        // Inline Definitions, not live Grant instances: the compiled container can only
        // dump an argument it knows how to (re)construct, not an already-built object.
        $grants = [
            new Definition(Grant::class, [[Grant::ACTION_PUBLISH], $publish]),
            new Definition(Grant::class, [[Grant::ACTION_SUBSCRIBE], $subscribe]),
        ];

        $tokenProvider = \sprintf('mercure.hub.%s.jwt.provider', $name);
        $container->register($tokenProvider, FactoryTokenProvider::class)
            ->addArgument(new Reference($tokenFactory))
            ->addArgument($grants)
            ->addTag('mercure.jwt.factory');

        return $tokenProvider;
    }

    /**
     * Registers the token factory configured under $configPath ("jwt", "publisher" or "subscriber"), and returns the ID of its lazy wrapper.
     *
     * @param array<string, mixed> $jwt
     * @param array<string, mixed> $hub
     */
    private function registerLazyTokenFactory(ContainerBuilder $container, string $name, string $factoryId, array $jwt, string $configPath, ProtocolVersion $protocolVersion, array $hub): string
    {
        $tokenFactory = $jwt['factory'] ?? $this->registerTokenFactory($container, $name, $factoryId, $jwt, $protocolVersion, $configPath);

        // "aud" defaults to the hub's own public identifier when not explicitly set;
        // required (along with "iss"/"sub"/"client_id") by RFC 9068 access tokens under protocol 1.0.
        // Baked into the factory itself, not just the token provider, so Authorization and the Twig
        // mercure() function, which call HubInterface::getFactory() directly, get these claims too.
        $defaultClaims = $jwt['claims'] ?? [];
        if (ProtocolVersion::V1 === $protocolVersion && null !== ($aud = $hub['public_url'] ?? $hub['url'] ?? null)) {
            $defaultClaims += ['aud' => $aud];
        }

        // No wrapping when there is nothing to merge: a 0.x hub without claims must
        // keep minting byte-identical legacy tokens (no "aud"), and a user-supplied
        // factory must pass through untouched.
        if ([] !== $defaultClaims) {
            $container->register("$factoryId.default_claims", DefaultClaimsTokenFactory::class)
                ->addArgument(new Reference($tokenFactory))
                ->addArgument($defaultClaims);
            $tokenFactory = "$factoryId.default_claims";
        }

        $container->register('.lazy.'.$tokenFactory, TokenFactoryInterface::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->addArgument([new Reference($tokenFactory), 'create']);

        return '.lazy.'.$tokenFactory;
    }

    /**
     * Registers the token factory built from "secret" or "jwks_uri" at $factoryId.
     *
     * @param array<string, mixed> $jwt
     */
    private function registerTokenFactory(ContainerBuilder $container, string $name, string $factoryId, array $jwt, ProtocolVersion $protocolVersion, string $configPath): string
    {
        // Fail at compile time rather than on the first minted token: RFC 9068 access tokens
        // require these claims, and LcobucciFactory's runtime exception can't point at the
        // bundle option to set. "aud" is exempt, it defaults to the hub's own URL.
        if (ProtocolVersion::V1 === $protocolVersion) {
            $missing = array_filter(['iss', 'sub', 'client_id'], static fn (string $claim): bool => empty($jwt['claims'][$claim]));
            if ([] !== $missing) {
                throw new InvalidConfigurationException(\sprintf('The "mercure.hubs.%1$s.%2$s.claims" option must define the "%3$s" claim(s): they are required by RFC 9068 access tokens when "protocol_version" is "1.0" and "%2$s.secret" or "%2$s.jwks_uri" is used.', $name, $configPath, implode('", "', $missing)));
            }
        }

        if (!isset($jwt['jwks_uri'])) {
            $container->register($factoryId, LcobucciFactory::class)
                ->addArgument($jwt['secret'])
                ->addArgument($jwt['algorithm'] ?? 'hmac.sha256')
                // RFC 9068 access tokens are expected to carry an "exp" claim; a resource server may
                // reject one that doesn't. The legacy "mercure" claim has no such expectation, so only
                // protocol 1.0 gets an automatic lifetime here, preserving the 0.x non-expiring default.
                ->addArgument(ProtocolVersion::V1 === $protocolVersion ? 0 : null)
                ->addArgument($jwt['passphrase'] ?? '')
                ->addArgument($protocolVersion)
                ->addTag('mercure.jwt.factory');

            return $factoryId;
        }

        if (!($this->webTokenLibraryInstalled ?? class_exists(JWK::class))) {
            throw new \LogicException(\sprintf('The "%1$s" hub is configured with "%2$s.jwks_uri", but the "web-token/jwt-library" package required to fetch the signing key from a JSON Web Key Set is not installed. Try running "composer require web-token/jwt-library", or configure "mercure.hubs.%1$s.%2$s.factory" to point to your own token factory service.', $name, $configPath));
        }

        $container->register($factoryId, WebTokenFactory::class)
            ->setFactory([WebTokenFactory::class, 'fromJwksUri'])
            ->setArgument('$jwksUri', $jwt['jwks_uri'])
            ->setArgument('$httpClient', new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$algorithm', $jwt['algorithm'] ?? 'HS256')
            ->setArgument('$keyId', $jwt['key_id'] ?? null)
            // this branch only exists for protocol 1.0 (the "jwks_uri" config option requires it),
            // so, unlike LcobucciFactory's, this lifetime default is unconditional; see the comment there.
            ->setArgument('$jwtLifetime', 0)
            ->addTag('mercure.jwt.factory');

        return $factoryId;
    }

    /**
     * @param array<string, mixed> $jwt
     *
     * @return array<string, mixed>
     */
    private function signingOptions(array $jwt): array
    {
        return array_intersect_key($jwt, array_flip(['secret', 'passphrase', 'algorithm', 'jwks_uri', 'key_id', 'claims']));
    }
}
