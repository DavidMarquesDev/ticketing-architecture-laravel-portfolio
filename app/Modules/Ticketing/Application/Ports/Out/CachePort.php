<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface CachePort
{
    public function getByKey(string $key): mixed;

    public function putByKey(string $key, mixed $value, int $seconds): void;

    public function forgetByKey(string $key): void;

    public function clear(): void;
}
