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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MercureBundle\LinkHeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

final class LinkHeaderUtilsTest extends TestCase
{
    public function testLinkAttributeIsSet(): void
    {
        $request = new Request();

        $linkHeaderUtils = new LinkHeaderUtils('default', [
            'default' => 'https://demo.mercure.rocks/hub',
        ]);

        $linkHeaderUtils->add($request);

        self::assertTrue($request->attributes->has('_links'));

        /** @var GenericLinkProvider $provider */
        $provider = $request->attributes->get('_links');

        self::assertCount(1, $provider->getLinks());
        self::assertCount(1, $provider->getLinksByRel('mercure'));
        self::assertSame('https://demo.mercure.rocks/hub', $provider->getLinksByRel('mercure')[0]->getHref());
    }

    public function testLinkAttributeIsAdded(): void
    {
        $provider = new GenericLinkProvider();
        $provider = $provider
            ->withLink(new Link('example', 'https://foo.example.com'))
            ->withLink(new Link('example', 'https://bar.example.com'))
            ->withLink(new Link('example', 'https://baz.example.com'))
        ;

        $request = new Request();
        $request->attributes->set('_links', $provider);

        $linkHeaderUtils = new LinkHeaderUtils('default', [
            'default' => 'https://demo.mercure.rocks/hub',
        ]);

        $linkHeaderUtils->add($request);

        self::assertTrue($request->attributes->has('_links'));

        /** @var GenericLinkProvider $newProvider */
        $newProvider = $request->attributes->get('_links');

        self::assertCount(4, $newProvider->getLinks());
        self::assertCount(1, $newProvider->getLinksByRel('mercure'));
        self::assertSame($provider->getLinksByRel('example'), $newProvider->getLinksByRel('example'));
        self::assertSame('https://demo.mercure.rocks/hub', $newProvider->getLinksByRel('mercure')[0]->getHref());
    }

    public function testLinkAttributeIsNotAddedToPreflightRequest(): void
    {
        $request = new Request();
        $request->setMethod('OPTIONS');
        $request->headers->set('Access-Control-Request-Method', 'POST');

        $linkHeaderUtils = new LinkHeaderUtils('default', [
            'default' => 'https://demo.mercure.rocks/hub',
        ]);

        $linkHeaderUtils->add($request);

        self::assertFalse($request->attributes->has('_links'));
    }
}
