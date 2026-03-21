<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Queue;

use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;

final class RedisQueueDispatcher implements QueueDispatcherPort
{
    public function dispatch(object $job): void
    {
        dispatch($job)->onConnection('redis');
    }
}
