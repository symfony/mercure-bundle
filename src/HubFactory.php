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
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the hub services that depend on "url" and "public_url" at runtime, once they are resolved.
 *
 * This cannot be done while the container is built: "url" usually comes from an
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
     * The publisher token is sent to "url", while a hub pinning its resource identifier
     * to "public_url" expects that one; it never reaches browsers, so it can carry both.
     *
     * @param Grant[]       $grants
     * @param list<?string> $audience
     */
    public static function createPublisherTokenProvider(TokenFactoryInterface $factory, array $grants, array $audience): FactoryTokenProvider
    {
        $audience = array_values(array_unique(array_filter($audience, static fn (?string $url): bool => null !== $url && '' !== $url)));

        return new FactoryTokenProvider($factory, $grants, match (\count($audience)) {
            0 => [],
            1 => ['aud' => $audience[0]],
            default => ['aud' => $audience],
        });
    }
}
