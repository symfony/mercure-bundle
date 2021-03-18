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

use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class Mercure
{
    private $defaultHub;
    private $hubs;
    private $publishers;
    private $factories;

    public function __construct(
        string $defaultHub,
        ServiceProviderInterface $hubs,
        ServiceProviderInterface $publishers,
        ServiceProviderInterface $factories
    ) {
        $this->defaultHub = $defaultHub;
        $this->hubs = $hubs;
        $this->publishers = $publishers;
        $this->factories = $factories;
    }

    public function getHub(?string $hub = null): Hub
    {
        $hub = $hub ?? $this->defaultHub;
        if (!$this->hubs->has($hub)) {
            throw new InvalidArgumentException(sprintf('Invalid hub name "%s", expected one of "%s".', $hub, implode('", "', array_keys($this->hubs->getProvidedServices()))));
        }

        return $this->hubs->get($hub);
    }

    public function getPublisher(?string $hub = null): PublisherInterface
    {
        $hub = $hub ?? $this->defaultHub;
        if (!$this->publishers->has($hub)) {
            throw new InvalidArgumentException(sprintf('Invalid hub name "%s", expected one of "%s".', $hub, implode('", "', array_keys($this->publishers->getProvidedServices()))));
        }

        return $this->publishers->get($hub);
    }

    public function getTokenFactory(?string $hub = null): TokenFactoryInterface
    {
        $hub = $hub ?? $this->defaultHub;
        if (!$this->factories->has($hub)) {
            throw new InvalidArgumentException(sprintf('A token factory is not defined for hub "%s".', $hub));
        }

        return $this->factories->get($hub);
    }
}
