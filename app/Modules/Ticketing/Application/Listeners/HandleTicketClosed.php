<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Listeners;

use App\Modules\Ticketing\Application\Jobs\PublishTicketLifecycleAuditJob;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Domain\Events\TicketClosed;

final class HandleTicketClosed
{
    public function __construct(
        private readonly TicketListCachePort $ticketListCache,
        private readonly QueueDispatcherPort $queueDispatcher
    ) {
    }

    public function handle(TicketClosed $event): void
    {
        $this->ticketListCache->forgetAll();
        $this->queueDispatcher->dispatch(
            new PublishTicketLifecycleAuditJob(
                ticketId: $event->ticketId,
                action: 'closed'
            )
        );
    }
}
