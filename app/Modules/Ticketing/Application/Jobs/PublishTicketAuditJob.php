<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

final class PublishTicketAuditJob
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $requesterId
    ) {
    }

    public function handle(): void
    {
        error_log(
            sprintf(
                'ticket.created.audit ticket_id=%s requester_id=%d',
                $this->ticketId,
                $this->requesterId
            )
        );
    }
}
