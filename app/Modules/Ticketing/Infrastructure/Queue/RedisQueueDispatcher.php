<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Queue;

use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;

final class RedisQueueDispatcher implements QueueDispatcherPort
{
    public function dispatch(object $job): void
    {
        if (method_exists($job, 'onConnection')) {
            $job->onConnection('redis');
        }

        if (function_exists('dispatch')) {
            call_user_func('dispatch', $job);
        }
    }
}
