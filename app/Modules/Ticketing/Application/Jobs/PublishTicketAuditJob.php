<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

use App\Modules\Ticketing\Infrastructure\Observability\StructuredLogger;

final class PublishTicketAuditJob
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $requesterId,
        public readonly ?string $traceId = null,
        public readonly ?string $correlationId = null
    ) {
    }

    public function handle(): void
    {
        StructuredLogger::log(
            type: 'ticket_audit',
            payload: [
                'action' => 'created',
                'ticket_id' => $this->ticketId,
                'requester_id' => $this->requesterId,
                'trace_id' => $this->traceId,
                'correlation_id' => $this->correlationId,
            ]
        );
    }
}
