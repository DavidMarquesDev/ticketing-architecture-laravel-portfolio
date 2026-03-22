<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Cache;

use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;

final class RedisTicketListCache implements TicketListCachePort
{
    private const KEYS_INDEX = 'ticket:list:keys';

    /**
     * @var array<string, mixed>
     */
    private static array $store = [];

    /**
     * @var array<string, int>
     */
    private static array $expiresAt = [];

    public function get(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): ?array
    {
        $key = $this->key($page, $perPage, $status, $requesterId, $assigneeId, $search, $sortBy, $sortDir);

        if ($this->isExpired($key)) {
            $this->forget($key);

            return null;
        }

        $value = self::$store[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    public function put(
        int $page,
        int $perPage,
        array $tickets,
        int $seconds,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): void
    {
        $key = $this->key($page, $perPage, $status, $requesterId, $assigneeId, $search, $sortBy, $sortDir);
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

    private function key(
        int $page,
        int $perPage,
        ?string $status,
        ?int $requesterId,
        ?int $assigneeId,
        ?string $search,
        string $sortBy,
        string $sortDir
    ): string
    {
        $searchToken = $search === null ? '-' : sha1(strtolower(trim($search)));

        return sprintf(
            'ticket:list:p%d:pp%d:st:%s:req:%s:asg:%s:sea:%s:sb:%s:sd:%s',
            $page,
            $perPage,
            $status ?? '-',
            $requesterId === null ? '-' : (string) $requesterId,
            $assigneeId === null ? '-' : (string) $assigneeId,
            $searchToken,
            $sortBy,
            $sortDir
        );
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
