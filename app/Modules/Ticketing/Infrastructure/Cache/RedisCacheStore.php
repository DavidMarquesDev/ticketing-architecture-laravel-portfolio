<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Cache;

use App\Modules\Ticketing\Application\Ports\Out\CachePort;

final class RedisCacheStore implements CachePort
{
    /**
     * @var array<string, mixed>
     */
    private static array $store = [];

    /**
     * @var array<string, int>
     */
    private static array $expiresAt = [];

    public function getByKey(string $key): mixed
    {
        if ($this->isExpired($key)) {
            $this->forgetByKey($key);

            return null;
        }

        return self::$store[$key] ?? null;
    }

    public function putByKey(string $key, mixed $value, int $seconds): void
    {
        self::$store[$key] = $value;
        self::$expiresAt[$key] = time() + max(1, $seconds);
    }

    public function forgetByKey(string $key): void
    {
        unset(self::$store[$key], self::$expiresAt[$key]);
    }

    public function clear(): void
    {
        self::$store = [];
        self::$expiresAt = [];
    }

    private function isExpired(string $key): bool
    {
        $expiresAt = self::$expiresAt[$key] ?? null;

        return is_int($expiresAt) && $expiresAt <= time();
    }
}
