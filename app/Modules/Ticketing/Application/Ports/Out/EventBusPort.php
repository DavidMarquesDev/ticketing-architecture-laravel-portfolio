<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface EventBusPort
{
    public function dispatch(object $event): void;
}
