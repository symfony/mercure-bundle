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

use Psr\Container\ContainerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\PublisherInterface;

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class PublisherChooser
{
    public function __construct(
        private ContainerInterface $publishers,
    ) {
    }

    public function choosePublisher(?string $hubUrl): PublisherInterface
    {
        if (null !== $hubUrl) {
            return $this->publishers->get('external');
        }

        throw new \RuntimeException(\sprintf('Unable to use "%s" with builtin Hub. Use "%s" instead.', PublisherInterface::class, HubInterface::class));
    }
}
