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

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\RemoteHubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Records the published updates instead of sending them to the hub.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 *
 * @internal
 */
final class RecordingHub implements RemoteHubInterface
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly UpdateRecorder $recorder,
        private readonly string $name,
    ) {
    }

    public function getUrl(): string
    {
        if ($this->hub instanceof RemoteHubInterface) {
            return $this->hub->getUrl();
        }

        throw new \LogicException(\sprintf('The "%s" Mercure hub is not a remote hub.', $this->name));
    }

    public function getPublicUrl(): string
    {
        return $this->hub->getPublicUrl();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->hub->getFactory();
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return $this->hub->getProtocolVersion();
    }

    public function getCookieName(): string
    {
        return $this->hub->getCookieName();
    }

    public function publish(Update $update): string
    {
        $this->recorder->record($this->name, $update);

        return $update->getId() ?? 'urn:uuid:'.self::uuid4();
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
