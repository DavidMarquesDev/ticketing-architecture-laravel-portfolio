<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\QueryHandlers;

use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsQueryHandler as GetTicketDetailsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use Throwable;

final class GetTicketDetailsQueryHandler implements GetTicketDetailsUseCase, GetTicketDetailsQueryHandlerPort
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    public function execute(GetTicketDetailsQuery $query): Ticket
    {
        $startedAt = microtime(true);

        try {
            $ticket = $this->ticketRepository->findById($query->ticketId);

            if ($ticket === null) {
                throw new TicketNotFoundException('Ticket não encontrado.');
            }

            $this->queryTelemetry->record(
                queryName: 'get_ticket_details',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $query->ticketId,
                ]
            );

            return $ticket;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'get_ticket_details',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $query->ticketId,
                    'exception' => $exception::class,
                ]
            );

            throw $exception;
        }
    }

    private function elapsedMilliseconds(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
