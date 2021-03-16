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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class Discovery
{
    private const MERCURE_AUTHORIZATION_COOKIE_NAME = 'mercureAuthorization';

    private $defaultHub;
    private $hubs;

    public function __construct(string $defaultHub, ServiceProviderInterface $hubs)
    {
        $this->defaultHub = $defaultHub;
        $this->hubs = $hubs;
    }

    /**
     * Add mercure link header to the given request.
     */
    public function addLink(Request $request, ?string $hub = null): void
    {
        // Prevent issues with NelmioCorsBundle
        if ($this->isPreflightRequest($request)) {
            return;
        }

        $hub = $hub ?? $this->defaultHub;

        if (!$this->hubs->has($hub)) {
            throw new InvalidArgumentException(sprintf('Invalid hub name "%s", expected one of "%s".', $hub, implode('", "', array_keys($this->hubs->getProvidedServices()))));
        }

        $url = $this->hubs->get($hub)->getPublicUrl();
        $link = new Link('mercure', $url);

        if (null === $linkProvider = $request->attributes->get('_links')) {
            $request->attributes->set('_links', new GenericLinkProvider([$link]));

            return;
        }

        $request->attributes->set('_links', $linkProvider->withLink($link));
    }

    private function isPreflightRequest(Request $request): bool
    {
        return $request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method');
    }
}
