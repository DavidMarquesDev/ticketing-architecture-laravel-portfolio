<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Listeners;

use App\Modules\Ticketing\Application\Jobs\PublishTicketLifecycleAuditJob;
use App\Modules\Ticketing\Application\Ports\Out\IntegrationEventPublisherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Domain\Events\TicketReplied;

final class HandleTicketReplied
{
    public function __construct(
        private readonly TicketListCachePort $ticketListCache,
        private readonly QueueDispatcherPort $queueDispatcher,
        private readonly IntegrationEventPublisherPort $integrationEventPublisher
    ) {
    }

    public function handle(TicketReplied $event): void
    {
        $this->ticketListCache->forgetAll();
        $this->queueDispatcher->dispatch(
            new PublishTicketLifecycleAuditJob(
                ticketId: $event->ticketId,
                action: 'replied',
                actorId: $event->authorId
            )
        );
        $this->integrationEventPublisher->publish(
            eventName: 'ticket.replied.v1',
            payload: [
                'ticket_id' => $event->ticketId,
                'comment_id' => $event->commentId,
                'author_id' => $event->authorId,
            ]
        );
    }
}
