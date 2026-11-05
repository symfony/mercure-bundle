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

/**
 * Assertions on the Mercure updates published during a test, for KernelTestCase and WebTestCase.
 *
 * The updates are only recorded, never sent, when "framework.test" is enabled.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 */
trait MercureAssertionsTrait
{
    /**
     * @param string|null $hub the name of the hub, null for all the hubs
     */
    public static function assertMercureUpdateCount(int $count, ?string $hub = null, string $message = ''): void
    {
        self::assertCount($count, self::getMercureUpdates($hub), $message);
    }

    /**
     * @param string|null $hub the name of the hub, null for all the hubs
     */
    public static function assertMercureUpdatePublished(string $topic, ?string $hub = null, string $message = ''): void
    {
        $topics = [];
        foreach (self::getMercureUpdates($hub) as $update) {
            array_push($topics, ...$update->getTopics());
        }

        self::assertContains($topic, $topics, $message);
    }

    /**
     * @param string|null $hub the name of the hub, null for all the hubs
     *
     * @return list<Update> in publishing order
     */
    public static function getMercureUpdates(?string $hub = null): array
    {
        $container = static::getContainer();
        if (!$container->has('mercure.test.update_recorder')) {
            static::fail('Mercure updates are only recorded when "framework.test" is enabled and at least one hub has its "record_updates_in_test" option enabled.');
        }

        return $container->get('mercure.test.update_recorder')->getUpdates($hub);
    }

    /**
     * @param string|null $hub the name of the hub, null for all the hubs
     */
    public static function getMercureUpdate(int $index = 0, ?string $hub = null): ?Update
    {
        return self::getMercureUpdates($hub)[$index] ?? null;
    }
}
