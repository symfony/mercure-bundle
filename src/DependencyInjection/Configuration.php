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
                                ->scalarNode('jwt')->info('JSON Web Token to use to publish to this hub.')->end()
                                ->scalarNode('jwt_provider')->info('The ID of a service to call to generate the JSON Web Token.')->end()
                                ->scalarNode('bus')->info('Name of the Messenger bus where the handler for this hub must be registered. Default to the default bus if Messenger is enabled.')->end()
                            ->end()
                            ->validate()
                                ->ifTrue(function ($v) { return isset($v['jwt']) && isset($v['jwt_provider']); })
                                ->thenInvalid('"jwt" and "jwt_provider" cannot be used together.')
                            ->end()
                        ->end()
                    ->end()
                    ->scalarNode('default_hub')->end()
                    ->booleanNode('enable_profiler')->info('Enable Symfony Web Profiler integration.')->defaultFalse()->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
