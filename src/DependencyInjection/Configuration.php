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
                    // no default: the two token factories name algorithms differently, so the default depends on which one "secret"/"jwks_uri" selects. See MercureExtension::registerTokenFactory().
                    ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT. With "secret", one of LcobucciFactory::SIGN_ALGORITHMS ("hmac.sha256", the default). With "jwks_uri", a JWA name from WebTokenFactory::SIGN_ALGORITHMS ("HS256", the default).')->end()
                    ->scalarNode('jwks_uri')->info('URL of a JSON Web Key Set (JWKS) to fetch the signing key from, instead of "secret". Requires "protocol_version: 1.0" and "web-token/jwt-library".')->end()
                    ->scalarNode('key_id')->info('The "kid" of the key to select from "jwks_uri", required when the key set holds more than one matching key.')->end()
                    ->arrayNode('claims')
                        ->useAttributeAsKey('name')
                        ->variablePrototype()->end()
                        ->info('Additional claims for the JWT built when using "secret" or "jwks_uri", e.g. "iss"/"sub"/"client_id", required by RFC 9068 access tokens under "protocol_version: 1.0". "aud" defaults to this hub\'s "public_url" (or "url") when not set here.')
                    ->end()
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
                    // no default: see the note on "publisher.algorithm" above.
                    ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT. With "secret", one of LcobucciFactory::SIGN_ALGORITHMS ("hmac.sha256", the default). With "jwks_uri", a JWA name from WebTokenFactory::SIGN_ALGORITHMS ("HS256", the default).')->end()
                    ->scalarNode('jwks_uri')->info('URL of a JSON Web Key Set (JWKS) to fetch the signing key from, instead of "secret". Requires "protocol_version: 1.0" and "web-token/jwt-library".')->end()
                    ->scalarNode('key_id')->info('The "kid" of the key to select from "jwks_uri", required when the key set holds more than one matching key.')->end()
                    ->arrayNode('claims')
                        ->useAttributeAsKey('name')
                        ->variablePrototype()->end()
                        ->info('Additional claims for the JWT built when using "secret" or "jwks_uri", e.g. "iss"/"sub"/"client_id", required by RFC 9068 access tokens under "protocol_version: 1.0". "aud" defaults to this hub\'s "public_url" (or "url") when not set here.')
                    ->end()
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
            ->info('JSON Web Token configuration. For separate publisher/subscriber signing keys, use the "publisher" and "subscriber" nodes instead.')
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
                    // no default: the two token factories name algorithms differently, so the default depends on which one "secret"/"jwks_uri" selects. See MercureExtension::registerTokenFactory().
                    ->scalarNode('algorithm')->info('The algorithm to use to sign the JWT. With "secret", one of LcobucciFactory::SIGN_ALGORITHMS ("hmac.sha256", the default). With "jwks_uri", a JWA name from WebTokenFactory::SIGN_ALGORITHMS ("HS256", the default).')->end()
                    ->scalarNode('jwks_uri')->info('URL of a JSON Web Key Set (JWKS) to fetch the signing key from, instead of "secret". Requires "protocol_version: 1.0" and "web-token/jwt-library".')->end()
                    ->scalarNode('key_id')->info('The "kid" of the key to select from "jwks_uri", required when the key set holds more than one matching key.')->end()
                    ->arrayNode('claims')
                        ->useAttributeAsKey('name')
                        ->variablePrototype()->end()
                        ->info('Additional claims for the JWT built when using "secret" or "jwks_uri", e.g. "iss"/"sub"/"client_id", required by RFC 9068 access tokens under "protocol_version: 1.0". "aud" defaults to this hub\'s "public_url" (or "url") when not set here; a 1.0 hub derives its expected audience from each request, so when publishing through an internal "url" distinct from "public_url", pin the hub\'s "resource_identifier" or set "aud" explicitly.')
                    ->end()
                ->end()
        ->end()
        ->scalarNode('jwt_provider')
            ->info('The ID of a service to call to generate the JSON Web Token.')
            ->setDeprecated('symfony/mercure-bundle', '0.3', 'The child node "%node%" at path "%path%" is deprecated, use "jwt.provider" instead.')
        ->end()
        ->scalarNode('bus')->info('Name of the Messenger bus where the handler for this hub must be registered. Default to the default bus if Messenger is enabled.')->end()
        ->scalarNode('http_client')->info('The ID of the HTTP client service to publish to this hub with, e.g. a scoped client with a short timeout. Defaults to "http_client".')->end()
        ->append(self::protocolVersionNode())
        ->scalarNode('cookie_name')
            ->defaultNull()
            ->info('Name of the subscriber authorization cookie. Defaults to a value computed from "protocol_version" when not set: "__Secure-mercure_access_token" for "1.0" ("mercure_access_token" in debug mode, matching the hub\'s "playground" mode), "mercureAuthorization" for "0.x".')
        ->end()
                            ->end()
                            // Reject mixing the legacy "jwt"/"jwt_provider" nodes with the new
                            // "publisher"/"subscriber" nodes. The legacy nodes stay fully functional
                            // (handled by their own branch in MercureExtension), they are just deprecated.
                            ->validate()
        ->ifTrue(static function ($v) { return (isset($v['jwt']) || isset($v['jwt_provider'])) && (isset($v['publisher']) || isset($v['subscriber'])); })
        ->thenInvalid('"jwt"/"jwt_provider" and "publisher"/"subscriber" cannot be used together. Migrate to "publisher"/"subscriber".')
                            ->end()
                            // A hub that publishes (has a "url") needs a way to sign: legacy "jwt"/"jwt_provider",
                            // or the new "publisher" node.
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['url']) && !isset($v['jwt']) && !isset($v['jwt_provider']) && !isset($v['publisher']); })
        ->thenInvalid('You must specify at least one of "publisher", "jwt", and "jwt_provider".')
                            ->end()
                            // Legacy "jwt"/"jwt_provider" validation (kept for BC).
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['jwt'], $v['jwt_provider']); })
        ->thenInvalid('"jwt" and "jwt_provider" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['jwt']['value'], $v['jwt']['provider']); })
        ->thenInvalid('"jwt.value" and "jwt.provider" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['jwt']) && !isset($v['jwt']['value']) && !isset($v['jwt']['provider']) && !isset($v['jwt']['factory']) && !isset($v['jwt']['secret']) && !isset($v['jwt']['jwks_uri']); })
        ->thenInvalid('You must specify at least one of "jwt.value", "jwt.provider", "jwt.factory", "jwt.secret", and "jwt.jwks_uri".')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['jwt']['secret'], $v['jwt']['jwks_uri']); })
        ->thenInvalid('"jwt.secret" and "jwt.jwks_uri" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['jwt']['jwks_uri']) && ProtocolVersion::V1 !== $v['protocol_version']; })
        ->thenInvalid('"jwt.jwks_uri" requires "protocol_version: 1.0", as it is only supported by WebTokenFactory.')
                            ->end()
                            // "publisher"/"subscriber" validation.
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['publisher']) && !isset($v['publisher']['value']) && !isset($v['publisher']['provider']) && !isset($v['publisher']['factory']) && !isset($v['publisher']['secret']) && !isset($v['publisher']['jwks_uri']); })
        ->thenInvalid('You must specify at least one of "publisher.value", "publisher.provider", "publisher.factory", "publisher.secret", and "publisher.jwks_uri".')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['publisher']['value'], $v['publisher']['provider']); })
        ->thenInvalid('"publisher.value" and "publisher.provider" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['publisher']['secret'], $v['publisher']['jwks_uri']); })
        ->thenInvalid('"publisher.secret" and "publisher.jwks_uri" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['subscriber']) && !isset($v['subscriber']['factory']) && !isset($v['subscriber']['secret']) && !isset($v['subscriber']['jwks_uri']); })
        ->thenInvalid('You must specify at least one of "subscriber.factory", "subscriber.secret", and "subscriber.jwks_uri".')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['subscriber']['secret'], $v['subscriber']['jwks_uri']); })
        ->thenInvalid('"subscriber.secret" and "subscriber.jwks_uri" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return (isset($v['publisher']['jwks_uri']) || isset($v['subscriber']['jwks_uri'])) && ProtocolVersion::V1 !== $v['protocol_version']; })
        ->thenInvalid('"publisher.jwks_uri"/"subscriber.jwks_uri" require "protocol_version: 1.0", as they are only supported by WebTokenFactory.')
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

    private static function protocolVersionNode(): EnumNodeDefinition
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
