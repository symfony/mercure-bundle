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

namespace Symfony\Bundle\MercureBundle;

use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Picks the hub implementation at runtime, once "url" is resolved.
 *
 * The choice cannot be made while the container is built: "url" usually comes from an
 * environment variable, which is still an unresolved placeholder at that point, and
 * FrankenPHP only defines mercure_publish() when serving a request, not under the CLI
 * running cache:clear.
 *
 * @internal
 */
final class HubFactory
{
    /**
     * Arguments mirror {@see Hub::__construct()}, with "url" and the token provider
     * made optional: a hub served by FrankenPHP's built-in Mercure has neither.
     */
    public static function create(
        ?string $url,
        ?TokenProviderInterface $tokenProvider,
        ?TokenFactoryInterface $tokenFactory,
        ?string $publicUrl,
        ?HttpClientInterface $httpClient,
        ?string $cookieName,
        ProtocolVersion $protocolVersion,
    ): HubInterface {
        if (null !== $url && '' !== $url) {
            if (null === $tokenProvider) {
                throw new \LogicException('A hub with a "url" needs a JSON Web Token to publish with: set one of the "jwt", "jwt.provider" or "jwt.factory" options.');
            }

            return new Hub($url, $tokenProvider, $tokenFactory, $publicUrl, $httpClient, $cookieName, $protocolVersion);
        }

        if (!\function_exists('mercure_publish')) {
            throw new \LogicException('No "url" is configured for this hub, which selects FrankenPHP\'s built-in Mercure hub, but the mercure_publish() function it publishes through is not available. Enable the "mercure" directive of your FrankenPHP server, or set the "url" option to the hub to publish to.');
        }

        if (null === $publicUrl || '' === $publicUrl) {
            throw new \LogicException('FrankenPHP\'s built-in Mercure hub needs the URL browsers subscribe to: set the "public_url" option.');
        }

        return new FrankenPhpHub($publicUrl, $tokenFactory, $cookieName, $protocolVersion);
    }

    /**
     * @deprecated along with the "mercure.hub.*.publisher" services it builds
     */
    public static function createPublisher(
        ?string $url,
        ?TokenProviderInterface $tokenProvider,
        ?HttpClientInterface $httpClient,
    ): PublisherInterface {
        if (null === $url || '' === $url) {
            throw new \LogicException('FrankenPHP\'s built-in Mercure hub cannot be published to through the deprecated Publisher, which needs a hub "url": use the hub service instead.');
        }

        if (null === $tokenProvider) {
            throw new \LogicException('A hub with a "url" needs a JSON Web Token to publish with: set one of the "jwt", "jwt.provider" or "jwt.factory" options.');
        }

        return new Publisher($url, $tokenProvider, $httpClient);
    }
}
