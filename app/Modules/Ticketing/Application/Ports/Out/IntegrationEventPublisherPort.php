<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface IntegrationEventPublisherPort
{
    public function publish(string $eventName, array $payload): void;
}
