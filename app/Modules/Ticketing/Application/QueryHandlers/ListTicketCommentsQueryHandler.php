<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\QueryHandlers;

use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsQueryHandler as ListTicketCommentsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Queries\ListTicketCommentsQuery;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use Throwable;

final class ListTicketCommentsQueryHandler implements ListTicketCommentsUseCase, ListTicketCommentsQueryHandlerPort
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketCommentRepositoryPort $ticketCommentRepository,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    public function execute(ListTicketCommentsQuery $query): array
    {
        $startedAt = microtime(true);

        try {
            $ticket = $this->ticketRepository->findById($query->ticketId);

            if ($ticket === null) {
                throw new TicketNotFoundException('Ticket não encontrado.');
            }

            $comments = $this->ticketCommentRepository->listByTicketId(
                ticketId: $query->ticketId,
                page: $query->page,
                perPage: $query->perPage
            );
            $this->queryTelemetry->record(
                queryName: 'list_ticket_comments',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $query->ticketId,
                    'page' => $query->page,
                    'per_page' => $query->perPage,
                    'count' => count($comments),
                ]
            );

            return $comments;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'list_ticket_comments',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $query->ticketId,
                    'page' => $query->page,
                    'per_page' => $query->perPage,
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
