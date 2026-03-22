<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface IntegrationEventPublisherPort
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $eventName, array $payload): void;
}
