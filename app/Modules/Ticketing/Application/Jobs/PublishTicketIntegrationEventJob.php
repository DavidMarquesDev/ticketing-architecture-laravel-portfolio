<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

use App\Modules\Ticketing\Infrastructure\Observability\StructuredLogger;

final class PublishTicketIntegrationEventJob
{
    public function __construct(
        public readonly string $eventName,
        public readonly array $payload
    ) {
    }

    public function handle(): void
    {
        StructuredLogger::log(
            type: 'ticket_integration_event',
            payload: [
                'event_name' => $this->eventName,
                'payload' => $this->payload,
                'trace_id' => is_string($this->payload['trace_id'] ?? null) ? $this->payload['trace_id'] : null,
                'correlation_id' => is_string($this->payload['correlation_id'] ?? null) ? $this->payload['correlation_id'] : null,
            ]
        );
    }
}
