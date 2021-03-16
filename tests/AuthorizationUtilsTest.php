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

namespace Symfony\Bundle\MercureBundle\Tests;

use Lcobucci\JWT\Signer\Key;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\AuthorizationUtils;
use Symfony\Bundle\MercureBundle\DependencyInjection\MercureExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Exception\RuntimeException;

final class AuthorizationUtilsTest extends TestCase
{
    public function testCreateCookie(): void
    {
        if (!class_exists(Key\InMemory::class)) {
            $this->markTestSkipped('requires lcobucci/jwt:^4.0.');
        }

        $authorizationUtils = $this->createAuthorizationUtils([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'public_url' => 'https://hub.example.com/.well-known/mercure',
                    'jwt' => [
                        'secret' => '!ChangeMe!',
                    ],
                ],
            ],
        ]);

        $request = new Request();
        $request->headers->set('HOST', 'example.com');
        $cookie = $authorizationUtils->createCookie($request, ['https://example.com/book/1.jsonld']);

        $this->assertSame('mercureAuthorization', $cookie->getName());
        $this->assertSame('/.well-known/mercure', $cookie->getPath());
        $this->assertSame('hub.example.com', $cookie->getDomain());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testCreateCookieSameDomain(): void
    {
        if (!class_exists(Key\InMemory::class)) {
            $this->markTestSkipped('requires lcobucci/jwt:^4.0.');
        }

        $authorizationUtils = $this->createAuthorizationUtils([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'public_url' => 'https://example.com/.well-known/mercure',
                    'jwt' => [
                        'secret' => '!ChangeMe!',
                    ],
                ],
            ],
        ]);

        $request = new Request();
        $request->headers->set('HOST', 'example.com');
        $cookie = $authorizationUtils->createCookie($request, ['https://example.com/book/1.jsonld']);

        $this->assertSame('mercureAuthorization', $cookie->getName());
        $this->assertSame('/.well-known/mercure', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testCreateCookieForExternalDomain(): void
    {
        if (!class_exists(Key\InMemory::class)) {
            $this->markTestSkipped('requires lcobucci/jwt:^4.0.');
        }

        $authorizationUtils = $this->createAuthorizationUtils([
            'hubs' => [
                'default' => [
                    'url' => 'https://demo.mercure.rocks/hub',
                    'public_url' => 'https://hub.foo.com/.well-known/mercure',
                    'jwt' => [
                        'secret' => '!ChangeMe!',
                    ],
                ],
            ],
        ]);

        $request = new Request();
        $request->headers->set('HOST', 'example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create authorization cookie for external domain "hub.foo.com".');

        $authorizationUtils->createCookie($request, ['https://example.com/book/1.jsonld']);
    }

    private function createAuthorizationUtils(array $configuration): AuthorizationUtils
    {
        $container = new ContainerBuilder();
        (new MercureExtension())->load(['mercure' => $configuration], $container);

        $container->getDefinition(AuthorizationUtils::class)->setPublic(true);
        $container->compile();

        return $container->get(AuthorizationUtils::class);
    }
}
