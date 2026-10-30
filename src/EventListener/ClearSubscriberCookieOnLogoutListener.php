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

namespace Symfony\Bundle\MercureBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Exception\ExceptionInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Clears the subscriber authorization cookie of every hub on logout, so that the browser can't keep
 * subscribing to the private topics of the user who logged out until the cookie or its token expires.
 *
 * @internal
 */
final class ClearSubscriberCookieOnLogoutListener
{
    private const ATTRIBUTE = '_mercure_logout_cookies';

    /**
     * @param \Closure(): HubRegistry   $hubs
     * @param \Closure(): Authorization $authorization
     */
    public function __construct(
        private readonly \Closure $hubs,
        private readonly \Closure $authorization,
    ) {
    }

    public function onLogout(LogoutEvent $event): void
    {
        try {
            $hubs = ($this->hubs)();
        } catch (\LogicException) {
            // a hub that can't be built (e.g. an empty "url" outside FrankenPHP) can't have set a cookie either
            return;
        }

        $request = $event->getRequest();
        $cookies = [];
        foreach ($hubs->all() as $name => $hub) {
            if (null === $hub->getFactory()) {
                continue;
            }

            try {
                $cookies[] = ($this->authorization)()->createClearCookie($request, $name);
            } catch (ExceptionInterface|\InvalidArgumentException) {
                // a hub on another second-level domain or with a reserved cookie name prefix its URL doesn't satisfy
            }
        }

        $request->attributes->set(self::ATTRIBUTE, $cookies);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $cookies = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (null === $cookies) {
            return;
        }

        $event->getRequest()->attributes->remove(self::ATTRIBUTE);
        foreach ($cookies as $cookie) {
            $event->getResponse()->headers->setCookie($cookie);
        }
    }
}
