<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

use App\Modules\Ticketing\Infrastructure\Observability\StructuredLogger;

final class PublishTicketLifecycleAuditJob
{
    public function __construct(
        public readonly string $ticketId,
        public readonly string $action,
        public readonly ?int $actorId = null,
        public readonly ?string $traceId = null,
        public readonly ?string $correlationId = null
    ) {
    }

    public function handle(): void
    {
        StructuredLogger::log(
            type: 'ticket_lifecycle_audit',
            payload: [
                'action' => $this->action,
                'ticket_id' => $this->ticketId,
                'actor_id' => $this->actorId,
                'trace_id' => $this->traceId,
                'correlation_id' => $this->correlationId,
            ]
        );
    }
}
