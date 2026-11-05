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

namespace Symfony\Bundle\MercureBundle\Test;

use Symfony\Component\Mercure\Update;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the updates published to every hub when "framework.test" is enabled.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 *
 * @internal
 */
final class UpdateRecorder implements ResetInterface
{
    /**
     * @var list<array{string, Update}>
     */
    private array $updates = [];

    /**
     * @param list<string> $hubs
     */
    public function __construct(
        private readonly array $hubs,
    ) {
    }

    public function record(string $hub, Update $update): void
    {
        $this->updates[] = [$hub, $update];
    }

    /**
     * @throws \InvalidArgumentException when the hub doesn't record its updates
     *
     * @return list<Update> the updates of the given hub, or of all the hubs when null, in publishing order
     */
    public function getUpdates(?string $hub = null): array
    {
        if (null !== $hub && !\in_array($hub, $this->hubs, true)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" Mercure hub does not record its updates: it is not defined or its "record_updates_in_test" option is disabled. Recording hubs: "%s".', $hub, implode('", "', $this->hubs)));
        }

        $updates = [];
        foreach ($this->updates as [$name, $update]) {
            if (null === $hub || $name === $hub) {
                $updates[] = $update;
            }
        }

        return $updates;
    }

    public function reset(): void
    {
        $this->updates = [];
    }
}
