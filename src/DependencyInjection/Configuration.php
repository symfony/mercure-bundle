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
        ->enumNode('protocol_version')
            ->values(array_column(ProtocolVersion::cases(), 'value'))
            ->defaultValue(ProtocolVersion::Legacy->value) // flip to V1 in a future release once Mercure hub 1.0 is tagged stable; nothing else here should need to change
            ->info('The Mercure protocol version spoken by this hub: "0.x" (default) or "1.0". Affects the default cookie name, the JWT claim shape built by "jwt.secret", and how the mercure() Twig function interprets matcher-typed topics.')
        ->end()
        ->scalarNode('cookie_name')
            ->defaultNull()
            ->info('Name of the subscriber authorization cookie. Defaults to a value computed from "protocol_version" ("mercureAuthorization" for "0.x", "__Secure-mercure_access_token" for "1.0") when not set.')
        ->end()
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['jwt'], $v['jwt_provider']); })
        ->thenInvalid('"jwt" and "jwt_provider" cannot be used together.')
                            ->end()
                            ->validate()
        ->ifTrue(static function ($v) { return isset($v['url']) && !isset($v['jwt']) && !isset($v['jwt_provider']); })
        ->thenInvalid('You must specify at least one of "jwt", and "jwt_provider".')
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
        ->ifTrue(static function ($v) { return isset($v['jwt']['jwks_uri']) && ProtocolVersion::V1->value !== $v['protocol_version']; })
        ->thenInvalid('"jwt.jwks_uri" requires "protocol_version: 1.0", as it is only supported by WebTokenFactory.')
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
