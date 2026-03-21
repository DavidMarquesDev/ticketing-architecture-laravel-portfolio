<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Events;

use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;

final class LaravelEventDispatcher implements EventDispatcherPort
{
    public function dispatch(object $event): void
    {
        if (function_exists('event')) {
            call_user_func('event', $event);
        }
    }
}
