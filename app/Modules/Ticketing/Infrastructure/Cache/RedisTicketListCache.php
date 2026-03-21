<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Cache;

use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;

final class RedisTicketListCache implements TicketListCachePort
{
    private const KEYS_INDEX = 'ticket:list:keys';

    private static array $store = [];

    private static array $expiresAt = [];

    public function get(int $page, int $perPage): ?array
    {
        $key = $this->key($page, $perPage);

        if ($this->isExpired($key)) {
            $this->forget($key);

            return null;
        }

        $value = self::$store[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    public function put(int $page, int $perPage, array $tickets, int $seconds): void
    {
        $key = $this->key($page, $perPage);
        $this->putValue($key, $tickets, $seconds);
        $keys = self::$store[self::KEYS_INDEX] ?? [];

        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->putValue(self::KEYS_INDEX, $keys, $seconds);
        }
    }

    public function forgetAll(): void
    {
        if ($this->isExpired(self::KEYS_INDEX)) {
            $this->forget(self::KEYS_INDEX);

            return;
        }

        $keys = self::$store[self::KEYS_INDEX] ?? [];

        if (!is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            if (is_string($key)) {
                $this->forget($key);
            }
        }

        $this->forget(self::KEYS_INDEX);
    }

    private function key(int $page, int $perPage): string
    {
        return sprintf('ticket:list:p%d:pp%d', $page, $perPage);
    }

    private function putValue(string $key, mixed $value, int $seconds): void
    {
        self::$store[$key] = $value;
        self::$expiresAt[$key] = time() + max(1, $seconds);
    }

    private function isExpired(string $key): bool
    {
        $expiresAt = self::$expiresAt[$key] ?? null;

        return is_int($expiresAt) && $expiresAt <= time();
    }

    private function forget(string $key): void
    {
        unset(self::$store[$key], self::$expiresAt[$key]);
    }
}
