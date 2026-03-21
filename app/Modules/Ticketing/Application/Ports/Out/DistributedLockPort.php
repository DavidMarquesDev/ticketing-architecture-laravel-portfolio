<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface DistributedLockPort
{
    public function execute(string $key, int $seconds, callable $callback): mixed;
}
