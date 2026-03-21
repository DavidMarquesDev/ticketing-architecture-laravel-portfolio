<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface EventDispatcherPort
{
    public function dispatch(object $event): void;
}
