<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Lock;

use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use RuntimeException;

final class RedisDistributedLock implements DistributedLockPort
{
    /**
     * @var array<string, int>
     */
    private static array $locks = [];

    public function execute(string $key, int $seconds, callable $callback): mixed
    {
        $expiresAt = self::$locks[$key] ?? 0;

        if ($expiresAt > time()) {
            throw new RuntimeException('Conflito de concorrência na operação de escrita.');
        }

        self::$locks[$key] = time() + $seconds;

        try {
            return $callback();
        } finally {
            unset(self::$locks[$key]);
        }
    }
}
