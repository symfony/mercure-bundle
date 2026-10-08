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

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\EnumNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * MercureExtension configuration structure.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
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
                                // both default to null: which hub implementation an empty "url" selects is
                                // decided at runtime by HubFactory, not here. See HubFactory::create().
                                ->scalarNode('url')->defaultNull()->info("URL of the hub's publish endpoint. Leave empty to publish through FrankenPHP's built-in Mercure hub.")->example('https://demo.mercure.rocks/.well-known/mercure')->end()
                                ->scalarNode('public_url')->defaultNull()->info("URL of the hub's public endpoint")->example('https://demo.mercure.rocks/.well-known/mercure')->end()
        ->append($this->publisherNode())
        ->append($this->subscriberNode())
        ->append($this->jwtNode())
        ->scalarNode('jwt_provider')
            ->info('The ID of a service to call to generate the JSON Web Token.')
            ->setDeprecated('symfony/mercure-bundle', '0.3', 'The child node "%node%" at path "%path%" is deprecated, use "jwt.provider" instead.')
        ->end()
        ->scalarNode('bus')->info('Name of the Messenger bus where the handler for this hub must be registered. Default to the default bus if Messenger is enabled.')->end()
        ->scalarNode('http_client')->info('The ID of the HTTP client service to publish to this hub with, e.g. a scoped client with a short timeout. Defaults to "http_client".')->end()
        ->append($this->protocolVersionNode())
        ->scalarNode('cookie_name')
            ->defaultNull()
            ->info('Name of the subscriber authorization cookie. Defaults to a value computed from "protocol_version" when not set: "__Secure-mercure_access_token" for "1.0" ("mercure_access_token" in debug mode, matching the hub\'s "playground" mode), "mercureAuthorization" for "0.x".')
        ->end()
                            ->end()
                            // rules spanning several options of the hub; each of "jwt", "publisher"
                            // and "subscriber" validates its own options, see their node methods
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => isset($v['jwt'], $v['jwt_provider']))
                                ->thenInvalid('"jwt" and "jwt_provider" cannot be used together.')
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => (isset($v['jwt']) || isset($v['jwt_provider'])) && (isset($v['publisher']) || isset($v['subscriber'])))
                                ->thenInvalid('"jwt"/"jwt_provider" and "publisher"/"subscriber" cannot be used together.')
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => isset($v['url']) && !isset($v['jwt']) && !isset($v['jwt_provider']) && !isset($v['publisher']))
                                ->thenInvalid('You must specify at least one of "publisher", "jwt", and "jwt_provider".')
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => ProtocolVersion::V1 !== $v['protocol_version'])
                                ->then(static function (array $v): array {
                                    foreach (['jwt', 'publisher', 'subscriber'] as $key) {
                                        if (isset($v[$key]['jwks_uri'])) {
                                            throw new \InvalidArgumentException(\sprintf('"%s.jwks_uri" requires "protocol_version: 1.0", as it is only supported by WebTokenFactory.', $key));
                                        }
                                    }

                                    return $v;
                                })
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

    private function publisherNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('publisher');
        $node
            ->info('Configuration of the JSON Web Token used to publish to this hub. Use with "subscriber" to sign publisher and subscriber tokens with different keys.')
            ->children()
                ->scalarNode('value')->info('JSON Web Token to use to publish to this hub.')->end()
                ->scalarNode('provider')->info('The ID of a service implementing TokenProviderInterface.')->end()
                ->scalarNode('factory')->info('The ID of a service implementing TokenFactoryInterface, used to create the JSON Web Token.')->end()
                ->arrayNode('topics')
                    ->beforeNormalization()->castToArray()->end()
                    ->scalarPrototype()->end()
                    ->info('A list of topics to allow publishing to when using the given factory to generate the JWT.')
                ->end();

        $this->forbidTogether($node, 'publisher', 'value', 'provider');
        $this->requireOneOf($node, 'publisher', 'value', 'provider', 'factory', 'secret', 'jwks_uri');

        return $this->addSigningNodes($node, 'publisher');
    }

    private function subscriberNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('subscriber');
        $node
            ->info('Configuration of the JSON Web Tokens created for subscribers (e.g. the authorization cookie).')
            ->children()
                ->scalarNode('factory')->info('The ID of a service implementing TokenFactoryInterface, used to create the JSON Web Tokens.')->end();

        $this->requireOneOf($node, 'subscriber', 'factory', 'secret', 'jwks_uri');

        return $this->addSigningNodes($node, 'subscriber');
    }

    private function jwtNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('jwt');
        $node
            ->beforeNormalization()
                ->ifString()
                ->then(static fn (string $token): array => [
                    'value' => $token,
                ])
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
                ->end();

        $this->forbidTogether($node, 'jwt', 'value', 'provider');
        $this->requireOneOf($node, 'jwt', 'value', 'provider', 'factory', 'secret', 'jwks_uri');

        return $this->addSigningNodes($node, 'jwt');
    }

    private function addSigningNodes(ArrayNodeDefinition $node, string $name): ArrayNodeDefinition
    {
        $this->forbidTogether($node, $name, 'secret', 'jwks_uri');

        $node
            ->children()
                ->scalarNode('secret')->info('The JWT Secret to use.')->example('!ChangeMe!')->end()
                ->scalarNode('passphrase')->info('The JWT secret passphrase.')->defaultValue('')->end()
                // no default: the two token factories name algorithms differently, so the default depends on which one "secret"/"jwks_uri" selects. See MercureExtension::registerTokenFactory().
                ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT. With "secret", one of LcobucciFactory::SIGN_ALGORITHMS ("hmac.sha256", the default). With "jwks_uri", a JWA name from WebTokenFactory::SIGN_ALGORITHMS ("HS256", the default).')->end()
                ->scalarNode('jwks_uri')->info('URL of a JSON Web Key Set (JWKS) to fetch the signing key from, instead of "secret". Requires "protocol_version: 1.0" and "web-token/jwt-library".')->end()
                ->scalarNode('key_id')->info('The "kid" of the key to select from "jwks_uri", required when the key set holds more than one matching key.')->end()
                ->arrayNode('claims')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                    ->info('Additional claims for the JWT built when using "secret" or "jwks_uri", e.g. "iss"/"sub"/"client_id", required by RFC 9068 access tokens under "protocol_version: 1.0". "aud" defaults to this hub\'s "public_url" (or "url") when not set here; a 1.0 hub derives its expected audience from each request, so when publishing through an internal "url" distinct from "public_url", pin the hub\'s "resource_identifier" or set "aud" explicitly.')
                ->end()
            ->end();

        return $node;
    }

    /**
     * Fails when none of $options is set on the $name node.
     */
    private function requireOneOf(ArrayNodeDefinition $node, string $name, string ...$options): void
    {
        $quoted = array_map(static fn (string $option): string => \sprintf('"%s.%s"', $name, $option), $options);
        $last = array_pop($quoted);

        $node
            ->validate()
                ->ifTrue(static fn (array $v): bool => [] === array_filter($options, static fn (string $option): bool => isset($v[$option])))
                ->thenInvalid(\sprintf('You must specify at least one of %s, and %s.', implode(', ', $quoted), $last))
            ->end();
    }

    /**
     * Fails when both $option and $otherOption are set on the $name node.
     */
    private function forbidTogether(ArrayNodeDefinition $node, string $name, string $option, string $otherOption): void
    {
        $node
            ->validate()
                ->ifTrue(static fn (array $v): bool => isset($v[$option], $v[$otherOption]))
                ->thenInvalid(\sprintf('"%1$s.%2$s" and "%1$s.%3$s" cannot be used together.', $name, $option, $otherOption))
            ->end();
    }

    private function protocolVersionNode(): EnumNodeDefinition
    {
        $node = new EnumNodeDefinition('protocol_version');

        // enumFqcn() requires symfony/config 7.3
        if (method_exists($node, 'enumFqcn')) {
            $node->enumFqcn(ProtocolVersion::class);
        } else {
            $node
                ->values(ProtocolVersion::cases())
                ->beforeNormalization()
                    ->ifString()
                    ->then(static fn (string $v): ProtocolVersion|string => ProtocolVersion::tryFrom($v) ?? $v)
                ->end();
        }

        return $node
            ->defaultValue(ProtocolVersion::V1)
            ->info('The Mercure protocol version spoken by this hub: "1.0" (default) or "0.x". Affects the default cookie name, the JWT claim shape built by "jwt.secret", and how the mercure() Twig function interprets matcher-typed topics.');
    }
}
