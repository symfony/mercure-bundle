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

namespace Symfony\Bundle\MercureBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Mercure\Debug\TraceableHub;
use Symfony\Component\Mercure\Debug\TraceablePublisher;

final class MercureDataCollector extends DataCollector
{
    /**
     * @var iterable<TraceablePublisher|TraceableHub>
     */
    private $hubs;

    /**
     * @param iterable<TraceablePublisher|TraceableHub> $hubs
     */
    public function __construct(iterable $hubs)
    {
        $this->hubs = $hubs;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data = [
            'count' => 0,
            'duration' => 0.0,
            'memory' => 0,
            'publishers' => [],
        ];

        foreach ($this->hubs as $name => $hub) {
            $this->data['hubs'][$name] = [
                'count' => $hub->count(),
                'duration' => $hub->getDuration(),
                'memory' => $hub->getMemory(),
                'messages' => $hub->getMessages(),
            ];

            $this->data['duration'] += $hub->getDuration();
            $this->data['memory'] += $hub->getMemory();
            $this->data['count'] += \count($hub->getMessages());
        }
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'mercure';
    }

    public function count(): int
    {
        return $this->data['count'];
    }

    public function getDuration(): float
    {
        return $this->data['duration'];
    }

    public function getMemory(): int
    {
        return $this->data['memory'];
    }

    public function getHubs(): iterable
    {
        return $this->data['hubs'];
    }

    /**
     * @deprecated use {@see MercureDataCollector::getHubs()} instead
     */
    public function getPublishers(): iterable
    {
        trigger_deprecation('symfony/mercure-bundle', '0.3', 'Method "%s::getPublishers()" is deprecated, use "%s::getHubs()" instead.', __CLASS__, __CLASS__);

        return $this->getHubs();
    }
}
