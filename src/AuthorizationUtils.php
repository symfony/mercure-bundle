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
use Symfony\Component\Mercure\Exception\RuntimeException;

final class AuthorizationUtils
{
    private const MERCURE_AUTHORIZATION_COOKIE_NAME = 'mercureAuthorization';

    private $mercure;

    public function __construct(Mercure $mercure)
    {
        $this->mercure = $mercure;
    }

    /**
     * Create Authorization cookie for the given hub.
     *
     * @param string[]    $subscribe        a list of topics that the authorization cookie will allow subscribing to
     * @param string[]    $publish          a list of topics that the authorization cookie will allow publishing to
     * @param mixed[]     $additionalClaims an array of additional claims for the JWT
     * @param string|null $hub              the hub to generate the cookie for
     */
    public function createCookie(Request $request, array $subscribe = [], array $publish = [], array $additionalClaims = [], ?string $hub = null): Cookie
    {
        $token = $this->mercure->getTokenFactory($hub)->create($subscribe, $publish, $additionalClaims);
        $url = $this->mercure->getHub($hub)->getPublicUrl();
        /** @var array $urlComponents */
        $urlComponents = parse_url($url);

        $cookie = Cookie::create(self::MERCURE_AUTHORIZATION_COOKIE_NAME)
            ->withValue($token)
            ->withPath(($urlComponents['path'] ?? '/'))
            ->withSecure('http' !== strtolower($urlComponents['scheme'] ?? 'https'))
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);

        if (isset($urlComponents['host'])) {
            $cookieDomain = strtolower($urlComponents['host']);
            $currentDomain = strtolower($request->getHost());

            if ($cookieDomain === $currentDomain) {
                return $cookie;
            }

            if (!str_ends_with($cookieDomain, ".${currentDomain}")) {
                throw new RuntimeException(sprintf('Unable to create authorization cookie for external domain "%s".', $cookieDomain));
            }

            $cookie = $cookie->withDomain($cookieDomain);
        }

        return $cookie;
    }
}
