<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Cache;

use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use Illuminate\Support\Facades\Cache;

final class RedisTicketListCache implements TicketListCachePort
{
    public function get(int $page, int $perPage): ?array
    {
        $value = Cache::store('redis')->get($this->key($page, $perPage));

        return is_array($value) ? $value : null;
    }

    public function put(int $page, int $perPage, array $tickets, int $seconds): void
    {
        Cache::store('redis')->put($this->key($page, $perPage), $tickets, $seconds);
        Cache::store('redis')->add('ticket:list:keys', []);
        $keys = Cache::store('redis')->get('ticket:list:keys', []);

        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($this->key($page, $perPage), $keys, true)) {
            $keys[] = $this->key($page, $perPage);
            Cache::store('redis')->put('ticket:list:keys', $keys, $seconds);
        }
    }

    public function forgetAll(): void
    {
        $keys = Cache::store('redis')->get('ticket:list:keys', []);

        if (!is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            if (is_string($key)) {
                Cache::store('redis')->forget($key);
            }
        }

        Cache::store('redis')->forget('ticket:list:keys');
    }

    private function key(int $page, int $perPage): string
    {
        return sprintf('ticket:list:p%d:pp%d', $page, $perPage);
    }
}
