<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

final class PublishTicketIntegrationEventJob
{
    public function __construct(
        public readonly string $eventName,
        public readonly array $payload
    ) {
    }

    public function handle(): void
    {
        $encodedPayload = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($encodedPayload)) {
            $encodedPayload = '{}';
        }

        error_log(
            sprintf(
                'ticket.integration.event name=%s payload=%s',
                $this->eventName,
                $encodedPayload
            )
        );
    }
}
