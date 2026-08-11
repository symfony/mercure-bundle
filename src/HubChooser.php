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

/**
 * @internal
 *
 * @author Stanislau Kviatkouski <7zete7@gmail.com>
 */
final class HubChooser
{
    public function __construct(
        private ContainerInterface $hubs,
    ) {
    }

    public function chooseHub(?string $hubUrl): HubInterface
    {
        return null === $hubUrl ? $this->hubs->get('builtin') : $this->hubs->get('external');
    }
}
