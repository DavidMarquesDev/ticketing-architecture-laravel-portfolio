<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Listeners;

use App\Modules\Ticketing\Application\Jobs\PublishTicketLifecycleAuditJob;
use App\Modules\Ticketing\Application\Ports\Out\IntegrationEventPublisherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Domain\Events\TicketAssigned;

final class HandleTicketAssigned
{
    public function __construct(
        private readonly TicketListCachePort $ticketListCache,
        private readonly QueueDispatcherPort $queueDispatcher,
        private readonly IntegrationEventPublisherPort $integrationEventPublisher
    ) {
    }

    public function handle(TicketAssigned $event): void
    {
        $this->ticketListCache->forgetAll();
        $this->queueDispatcher->dispatch(
            new PublishTicketLifecycleAuditJob(
                ticketId: $event->ticketId,
                action: 'assigned',
                actorId: $event->actorUserId
            )
        );
        $this->integrationEventPublisher->publish(
            eventName: 'ticket.assigned.v1',
            payload: [
                'ticket_id' => $event->ticketId,
                'assignee_id' => $event->assigneeId,
                'actor_user_id' => $event->actorUserId,
            ]
        );
    }
}
