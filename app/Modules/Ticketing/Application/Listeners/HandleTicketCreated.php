<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Listeners;

use App\Modules\Ticketing\Application\Jobs\PublishTicketAuditJob;
use App\Modules\Ticketing\Application\Ports\Out\IntegrationEventPublisherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Domain\Events\TicketCreated;

final class HandleTicketCreated
{
    public function __construct(
        private readonly TicketListCachePort $ticketListCache,
        private readonly QueueDispatcherPort $queueDispatcher,
        private readonly IntegrationEventPublisherPort $integrationEventPublisher
    ) {
    }

    public function handle(TicketCreated $event): void
    {
        $this->ticketListCache->forgetAll();

        $this->queueDispatcher->dispatch(
            new PublishTicketAuditJob(
                ticketId: $event->ticketId,
                requesterId: $event->requesterId
            )
        );

        $this->integrationEventPublisher->publish(
            eventName: 'ticket.created.v1',
            payload: [
                'ticket_id' => $event->ticketId,
                'requester_id' => $event->requesterId,
            ]
        );
    }
}
