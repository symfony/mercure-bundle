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

final class LinkHeaderUtils
{
    private $defaultHub;
    private $hubs;

    /**
     * @param string[] $hubs
     */
    public function __construct(string $defaultHub, array $hubs)
    {
        $this->defaultHub = $defaultHub;
        $this->hubs = $hubs;
    }

    /**
     * Add mercure link header to the given request.
     *
     * @param string|null $hub the hub name, if null, the default hub will be used
     */
    public function add(Request $request, string $hub = null): void
    {
        // Prevent issues with NelmioCorsBundle
        if ($this->isPreflightRequest($request)) {
            return;
        }

        if (null !== $hub && !isset($this->hubs[$hub])) {
            throw new InvalidArgumentException(sprintf('Invalid hub name "%s", expected one of "%s".', $hub, implode('", "', array_keys($this->hubs))));
        }

        $hub = $hub ?? $this->defaultHub;
        $url = $this->hubs[$hub];
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
