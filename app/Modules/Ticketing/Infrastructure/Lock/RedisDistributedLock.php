<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Lock;

use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class RedisDistributedLock implements DistributedLockPort
{
    public function execute(string $key, int $seconds, callable $callback): mixed
    {
        $lock = Cache::store('redis')->lock($key, $seconds);

        try {
            return $lock->block($seconds, $callback);
        } catch (LockTimeoutException) {
            throw new RuntimeException('Conflito de concorrência na operação de escrita.');
        } finally {
            optional($lock)->release();
        }
    }
}
