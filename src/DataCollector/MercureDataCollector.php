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
use Symfony\Component\Mercure\Debug\TraceablePublisher;

final class MercureDataCollector extends DataCollector
{
    private $publishers;

    /**
     * @var TraceablePublisher[]
     */
    public function __construct(iterable $publishers)
    {
        $this->publishers = $publishers;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data = [
            'count' => 0,
            'duration' => 0.0,
            'memory' => 0,
            'publishers' => [],
        ];

        foreach ($this->publishers as $name => $publisher) {
            $this->data['publishers'][$name] = [
                'count' => $publisher->count(),
                'duration' => $publisher->getDuration(),
                'memory' => $publisher->getMemory(),
                'messages' => $publisher->getMessages(),
            ];

            $this->data['duration'] += $publisher->getDuration();
            $this->data['memory'] += $publisher->getMemory();
            $this->data['count'] += \count($publisher->getMessages());
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

    public function getPublishers(): iterable
    {
        return $this->data['publishers'];
    }
}
