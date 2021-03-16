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
        $request->headers->set('HOST', 'https://example.com');
        $cookie = $authorizationUtils->createCookie($request, ['https://example.com/book/1.jsonld']);

        self::assertSame('mercureAuthorization', $cookie->getName());
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertSame('example.com', $cookie->getDomain());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('example.com', $cookie->getDomain());
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
