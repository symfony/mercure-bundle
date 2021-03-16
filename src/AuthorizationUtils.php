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

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class AuthorizationUtils
{
    private const MERCURE_AUTHORIZATION_COOKIE_NAME = 'mercureAuthorization';

    private $defaultHub;
    private $hubs;
    private $factories;

    public function __construct(string $defaultHub, ServiceProviderInterface $hubs, ServiceProviderInterface $factories)
    {
        $this->defaultHub = $defaultHub;
        $this->hubs = $hubs;
        $this->factories = $factories;
    }

    /**
     * Create Authorization cookie for the given hub.
     *
     * @param string[]    $subscribe        a list of topics that the authorization cookie will allow subscribing to
     * @param string[]    $publish          a list of topics that the authorization cookie will allow publishing to
     * @param string|null $hub              the hub to generate the cookie for
     * @param mixed[]     $additionalClaims an array of additional claims for the JWT
     */
    public function createCookie(Request $request, array $subscribe = [], array $publish = [], ?string $hub = null, array $additionalClaims = []): Cookie
    {
        $hub = $hub ?? $this->defaultHub;
        if (!$this->hubs->has($hub)) {
            throw new InvalidArgumentException(sprintf('Invalid hub name "%s", expected one of "%s".', $hub, implode('", "', array_keys($this->hubs->getProvidedServices()))));
        }

        if (!$this->factories->has($hub)) {
            throw new InvalidArgumentException(sprintf('A token factory is not defined for hub "%s".', $hub));
        }

        $token = $this->factories->get($hub)->create($subscribe, $publish, $additionalClaims);

        $url = $this->hubs->get($hub)->getPublicUrl();
        /** @var array $urlComponents */
        $urlComponents = parse_url($url);

        $cookie = Cookie::create(self::MERCURE_AUTHORIZATION_COOKIE_NAME)
            ->withValue($token)
            ->withPath(($urlComponents['path'] ?? '/'))
            ->withSecure('http' !== strtolower($urlComponents['scheme'] ?? 'https'))
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);

        if (isset($urlComponents['host'])) {
            $cookieDomain = $urlComponents['host'];
            $currentDomain = $request->getHost();

            if (!str_ends_with(strtolower($cookieDomain), strtolower($currentDomain))) {
                throw new RuntimeException(sprintf('Unable to create authorization cookie for external domain "%s".', $currentDomain));
            }

            $cookie = $cookie->withDomain($cookieDomain);
        }

        return $cookie;
    }
}
