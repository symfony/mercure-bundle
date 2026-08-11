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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Mercure\FrankenPhpHub;

/**
 * MercureExtension configuration structure.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builtinPublish = class_exists(FrankenPhpHub::class) && \function_exists('mercure_publish');

        $treeBuilder = new TreeBuilder('mercure');
        $rootNode = $treeBuilder->getRootNode();

        $urlNode = $rootNode
                ->fixXmlConfig('hub')
                ->children()
                    ->arrayNode('hubs')
                        ->useAttributeAsKey('name')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->beforeNormalization()
                                ->always(static function ($v) {
                                    if (!\is_array($v)) {
                                        return $v;
                                    }

                                    // Reject mixing legacy and new config
                                    $hasLegacy = isset($v['jwt']) || isset($v['jwt_provider']);
                                    $hasNew = isset($v['publisher']) || isset($v['subscriber']);
                                    if ($hasLegacy && $hasNew) {
                                        throw new \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException('"jwt"/"jwt_provider" and "publisher"/"subscriber" cannot be used together. Migrate to "publisher"/"subscriber".');
                                    }

                                    // Normalize legacy jwt_provider → publisher.provider
                                    if (isset($v['jwt_provider'])) {
                                        $v['publisher']['provider'] = $v['jwt_provider'];
                                        unset($v['jwt_provider']);
                                    }

                                    // Normalize legacy jwt → publisher + subscriber
                                    if (isset($v['jwt'])) {
                                        $jwt = $v['jwt'];

                                        // jwt as string shorthand
                                        if (\is_string($jwt)) {
                                            $jwt = ['value' => $jwt];
                                        }

                                        if (!isset($v['publisher'])) {
                                            $publisher = [];

                                            if (isset($jwt['value'])) {
                                                $publisher['value'] = $jwt['value'];
                                            }
                                            if (isset($jwt['provider'])) {
                                                $publisher['provider'] = $jwt['provider'];
                                            }
                                            if (isset($jwt['factory'])) {
                                                $publisher['factory'] = $jwt['factory'];
                                            }
                                            if (isset($jwt['secret'])) {
                                                $publisher['secret'] = $jwt['secret'];
                                            }
                                            if (isset($jwt['algorithm'])) {
                                                $publisher['algorithm'] = $jwt['algorithm'];
                                            }
                                            if (isset($jwt['passphrase'])) {
                                                $publisher['passphrase'] = $jwt['passphrase'];
                                            }
                                            if (isset($jwt['publish'])) {
                                                $publisher['topics'] = $jwt['publish'];
                                            }

                                            $v['publisher'] = $publisher;
                                        }

                                        if (!isset($v['subscriber'])) {
                                            $subscriber = [];

                                            if (isset($jwt['factory'])) {
                                                $subscriber['factory'] = $jwt['factory'];
                                            }
                                            if (isset($jwt['secret'])) {
                                                $subscriber['secret'] = $jwt['secret'];
                                            }
                                            if (isset($jwt['algorithm'])) {
                                                $subscriber['algorithm'] = $jwt['algorithm'];
                                            }
                                            if (isset($jwt['passphrase'])) {
                                                $subscriber['passphrase'] = $jwt['passphrase'];
                                            }
                                            if (isset($jwt['subscribe'])) {
                                                $subscriber['topics'] = $jwt['subscribe'];
                                            }

                                            // Only set subscriber if there's something to subscribe with
                                            if (!empty($subscriber)) {
                                                $v['subscriber'] = $subscriber;
                                            }
                                        }

                                        unset($v['jwt']);
                                    }

                                    return $v;
                                })
                            ->end()
                            ->children()
                                ->scalarNode('url')->info('URL of the hub\'s publish endpoint')->example('https://demo.mercure.rocks/.well-known/mercure');

        if ($builtinPublish) {
            $urlNode->defaultNull();
        }

        $publicUrlNode = $urlNode->end()
        ->scalarNode('public_url')->info('URL of the hub\'s public endpoint')->example('https://demo.mercure.rocks/.well-known/mercure');

        if (!$builtinPublish) {
            $publicUrlNode->defaultNull();
        }

        $publicUrlNode->end()
        ->arrayNode('publisher')
            ->info('Publisher JWT configuration (TokenProviderInterface).')
                ->children()
                    ->scalarNode('value')->info('Static JSON Web Token to use to publish to this hub.')->end()
                    ->scalarNode('provider')->info('The ID of a service implementing TokenProviderInterface.')->end()
                    ->scalarNode('factory')->info('The ID of a service implementing TokenFactoryInterface, wrapped as FactoryTokenProvider.')->end()
                    ->arrayNode('topics')
                        ->beforeNormalization()->castToArray()->end()
                        ->scalarPrototype()->end()
                        ->info('A list of topics for the mercure.publish claim in the publisher JWT.')
                    ->end()
                    ->scalarNode('secret')->info('The JWT secret to use.')->example('!ChangeMe!')->end()
                    ->scalarNode('passphrase')->info('The JWT secret passphrase.')->defaultValue('')->end()
                    ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT.')->defaultValue('hmac.sha256')->end()
                ->end()
        ->end()
        ->arrayNode('subscriber')
            ->info('Subscriber JWT configuration (TokenFactoryInterface).')
                ->children()
                    ->scalarNode('factory')->info('The ID of a service implementing TokenFactoryInterface.')->end()
                    ->arrayNode('topics')
                        ->beforeNormalization()->castToArray()->end()
                        ->scalarPrototype()->end()
                        ->info('A list of topics for the mercure.subscribe claim in the publisher JWT.')
                    ->end()
                    ->scalarNode('secret')->info('The JWT secret to use.')->example('!ChangeMe!')->end()
                    ->scalarNode('passphrase')->info('The JWT secret passphrase.')->defaultValue('')->end()
                    ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT.')->defaultValue('hmac.sha256')->end()
                ->end()
        ->end()
        ->arrayNode('jwt')
            ->beforeNormalization()
                ->ifString()
                ->then(static function (string $token): array {
                    return [
                        'value' => $token,
                    ];
                })
            ->end()
            ->setDeprecated('symfony/mercure-bundle', '0.5', 'The child node "%node%" at path "%path%" is deprecated, use "publisher" and "subscriber" instead.')
            ->info('JSON Web Token configuration (deprecated, use "publisher" and "subscriber" instead).')
                ->children()
                    ->scalarNode('value')->info('JSON Web Token to use to publish to this hub.')->end()
                    ->scalarNode('provider')->info('The ID of a service to call to provide the JSON Web Token.')->end()
                    ->scalarNode('factory')->info('The ID of a service to call to create the JSON Web Token.')->end()
                    ->arrayNode('publish')
                        ->beforeNormalization()->castToArray()->end()
                        ->scalarPrototype()->end()
                        ->info('A list of topics to allow publishing to when using the given factory to generate the JWT.')
                    ->end()
                    ->arrayNode('subscribe')
                        ->beforeNormalization()->castToArray()->end()
                        ->scalarPrototype()->end()
                        ->info('A list of topics to allow subscribing to when using the given factory to generate the JWT.')
                    ->end()
                    ->scalarNode('secret')->info('The JWT Secret to use.')->example('!ChangeMe!')->end()
                    ->scalarNode('passphrase')->info('The JWT secret passphrase.')->defaultValue('')->end()
                    ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT')->defaultValue('hmac.sha256')->end()
                ->end()
        ->end()
        ->scalarNode('jwt_provider')
            ->info('The ID of a service to call to generate the JSON Web Token.')
            ->setDeprecated('symfony/mercure-bundle', '0.3', 'The child node "%node%" at path "%path%" is deprecated, use "publisher.provider" instead.')
        ->end()
        ->scalarNode('bus')->info('Name of the Messenger bus where the handler for this hub must be registered. Default to the default bus if Messenger is enabled.')->end()
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['url']) && !isset($v['publisher']); })
        ->thenInvalid('You must specify a "publisher" configuration.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['publisher']) && !isset($v['publisher']['value']) && !isset($v['publisher']['provider']) && !isset($v['publisher']['factory']) && !isset($v['publisher']['secret']); })
        ->thenInvalid('You must specify at least one of "publisher.value", "publisher.provider", "publisher.factory", and "publisher.secret".')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['publisher']['value'], $v['publisher']['provider']); })
        ->thenInvalid('"publisher.value" and "publisher.provider" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['subscriber']) && !isset($v['subscriber']['factory']) && !isset($v['subscriber']['secret']); })
        ->thenInvalid('You must specify at least one of "subscriber.factory" and "subscriber.secret".')
                            ->end()
                        ->end()
                    ->end()
                    ->scalarNode('default_hub')->end()
                    ->integerNode('default_cookie_lifetime')->defaultNull()->info('Default lifetime of the cookie containing the JWT, in seconds. Defaults to the value of "framework.session.cookie_lifetime".')->end()
                    ->booleanNode('enable_profiler')->info('Enable Symfony Web Profiler integration.')->setDeprecated('symfony/mercure-bundle', '0.3')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
