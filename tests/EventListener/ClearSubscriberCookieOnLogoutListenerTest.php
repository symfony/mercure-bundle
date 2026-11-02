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

namespace Symfony\Bundle\MercureBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\EventListener\ClearSubscriberCookieOnLogoutListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class ClearSubscriberCookieOnLogoutListenerTest extends TestCase
{
    public function testClearsTheCookieOfEveryHub()
    {
        $response = $this->logout([
            'default' => $this->createHub('https://example.com/.well-known/mercure'),
            'other' => $this->createHub('https://hub.example.com/.well-known/mercure', 'other_access_token'),
        ]);

        $cookies = $response->headers->getCookies();
        $this->assertCount(2, $cookies);

        [$default, $other] = $cookies;
        $this->assertSame('__Secure-mercure_access_token', $default->getName());
        $this->assertSame('/.well-known/mercure', $default->getPath());
        $this->assertNull($default->getDomain());
        $this->assertTrue($default->isCleared());

        $this->assertSame('other_access_token', $other->getName());
        $this->assertSame('example.com', $other->getDomain());
        $this->assertTrue($other->isCleared());
    }

    public function testOverridesACookieSetDuringTheLogoutRequest()
    {
        $hubs = ['default' => $this->createHub('https://example.com/.well-known/mercure')];
        $registry = new HubRegistry($hubs['default'], $hubs);
        $authorization = new Authorization($registry);

        $response = new Response();
        $response->headers->setCookie($authorization->createCookie(Request::create('https://example.com/'), ['https://example.com/private']));

        $this->logout($hubs, response: $response);

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertTrue($cookies[0]->isCleared());
    }

    public function testSkipsAHubWithoutTokenFactory()
    {
        $response = $this->logout([
            'default' => $this->createHub('https://example.com/.well-known/mercure', factory: false),
        ]);

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testSkipsAHubOnAnotherSecondLevelDomain()
    {
        $response = $this->logout([
            'default' => $this->createHub('https://example.com/.well-known/mercure'),
            'remote' => $this->createHub('https://mercure.example.org/.well-known/mercure', 'remote_access_token'),
        ]);

        $this->assertSame(['__Secure-mercure_access_token'], $this->cookieNames($response));
    }

    public function testSkipsASecureCookieOverPlainHttp()
    {
        $response = $this->logout([
            'default' => $this->createHub('http://example.com/.well-known/mercure'),
        ], 'http://example.com/logout');

        $this->assertSame([], $response->headers->getCookies());
    }

    public function testSkipsAHostPrefixedCookieOutsideTheRootPath()
    {
        try {
            Cookie::create('__Host-test', null, 1, '/path', null, true);
            $this->markTestSkipped('requires symfony/http-foundation 8.2, which rejects a "__Host-" cookie outside the root path.');
        } catch (\InvalidArgumentException) {
        }

        $response = $this->logout([
            'default' => $this->createHub('https://example.com/.well-known/mercure'),
            'host' => $this->createHub('https://example.com/.well-known/mercure', '__Host-mercure_access_token'),
        ]);

        $this->assertSame(['__Secure-mercure_access_token'], $this->cookieNames($response));
    }

    public function testDoesNothingWhenTheHubsCannotBeBuilt()
    {
        $listener = new ClearSubscriberCookieOnLogoutListener(
            static fn () => throw new \LogicException('No "url" is configured for this hub.'),
            static fn () => throw new \LogicException('No "url" is configured for this hub.'),
        );

        $request = Request::create('https://example.com/logout');
        $listener->onLogout(new LogoutEvent($request, null));
        $listener->onKernelResponse($event = $this->createResponseEvent($request, new Response()));

        $this->assertSame([], $event->getResponse()->headers->getCookies());
    }

    public function testDoesNothingWithoutLogout()
    {
        $registry = new HubRegistry($this->createHub('https://example.com/.well-known/mercure'));
        $listener = new ClearSubscriberCookieOnLogoutListener(static fn () => $registry, static fn () => new Authorization($registry));

        $listener->onKernelResponse($event = $this->createResponseEvent(Request::create('https://example.com/'), new Response()));

        $this->assertSame([], $event->getResponse()->headers->getCookies());
    }

    /**
     * @param array<string, Hub> $hubs
     */
    private function logout(array $hubs, string $uri = 'https://example.com/logout', Response $response = new Response()): Response
    {
        $registry = new HubRegistry(reset($hubs), $hubs);
        $listener = new ClearSubscriberCookieOnLogoutListener(static fn () => $registry, static fn () => new Authorization($registry));

        $request = Request::create($uri);
        $listener->onLogout(new LogoutEvent($request, null));
        $listener->onKernelResponse($this->createResponseEvent($request, $response));

        return $response;
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    /**
     * @return string[]
     */
    private function cookieNames(Response $response): array
    {
        return array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies());
    }

    private function createHub(string $publicUrl, ?string $cookieName = null, bool $factory = true): Hub
    {
        $tokenFactory = $factory ? new class implements TokenFactoryInterface {
            public function create(array $grants = [], array $additionalClaims = []): string
            {
                return 'token';
            }
        } : null;

        // symfony/mercure 0.8 defaults to "0.x"
        return new Hub($publicUrl, new StaticTokenProvider('token'), $tokenFactory, $publicUrl, null, $cookieName, ProtocolVersion::V1);
    }
}
