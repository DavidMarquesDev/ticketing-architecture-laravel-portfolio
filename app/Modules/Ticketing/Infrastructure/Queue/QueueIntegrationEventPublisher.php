<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Queue;

use App\Modules\Ticketing\Application\Jobs\PublishTicketIntegrationEventJob;
use App\Modules\Ticketing\Application\Ports\Out\IntegrationEventPublisherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;

final class QueueIntegrationEventPublisher implements IntegrationEventPublisherPort
{
    public function __construct(
        private readonly QueueDispatcherPort $queueDispatcher
    ) {
    }

    public function publish(string $eventName, array $payload): void
    {
        $this->queueDispatcher->dispatch(
            new PublishTicketIntegrationEventJob(
                eventName: $eventName,
                payload: $payload
            )
        );
    }
}
