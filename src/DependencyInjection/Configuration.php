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

/**
 * MercureExtension configuration structure.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('mercure');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
                ->fixXmlConfig('hub')
                ->children()
                    ->arrayNode('hubs')
                        ->useAttributeAsKey('name')
                        ->normalizeKeys(false)
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('url')->info('URL of the hub\'s publish endpoint')->example('https://demo.mercure.rocks/.well-known/mercure')->end()
                                ->scalarNode('public_url')->info('URL of the hub\'s public endpoint')->example('https://demo.mercure.rocks/.well-known/mercure')->defaultNull()->end()
                                ->arrayNode('jwt')
                                    ->beforeNormalization()
                                        ->ifString()
                                        ->then(static function (string $token): array {
                                            return [
                                                'value' => $token,
                                            ];
                                        })
                                    ->end()
                                    ->info('JSON Web Token configuration.')
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
                                    ->setDeprecated('symfony/mercure-bundle', '0.3', 'The child node "%node%" at path "%path%" is deprecated, use "jwt.provider" instead.')
                                ->end()
                                ->scalarNode('bus')->info('Name of the Messenger bus where the handler for this hub must be registered. Default to the default bus if Messenger is enabled.')->end()
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) { return isset($v['jwt'], $v['jwt_provider']); })
                                ->thenInvalid('"jwt" and "jwt_provider" cannot be used together.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) { return !isset($v['jwt']) && !isset($v['jwt_provider']); })
                                ->thenInvalid('You must specify at least one of "jwt", and "jwt_provider".')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) { return isset($v['jwt']['value'], $v['jwt']['provider']); })
                                ->thenInvalid('"jwt.value" and "jwt.provider" cannot be used together.')
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) { return isset($v['jwt']) && !isset($v['jwt']['value']) && !isset($v['jwt']['provider']) && !isset($v['jwt']['factory']) && !isset($v['jwt']['secret']); })
                                ->thenInvalid('You must specify at least one of "jwt.value", "jwt.provider", "jwt.factory", and "jwt.secret".')
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
