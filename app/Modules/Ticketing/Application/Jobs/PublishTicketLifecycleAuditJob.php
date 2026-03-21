<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

final class PublishTicketLifecycleAuditJob
{
    public function __construct(
        public readonly string $ticketId,
        public readonly string $action,
        public readonly ?int $actorId = null
    ) {
    }

    public function handle(): void
    {
        $actor = $this->actorId === null ? 'null' : (string) $this->actorId;

        error_log(
            sprintf(
                'ticket.lifecycle.audit action=%s ticket_id=%s actor_id=%s',
                $this->action,
                $this->ticketId,
                $actor
            )
        );
    }
}
