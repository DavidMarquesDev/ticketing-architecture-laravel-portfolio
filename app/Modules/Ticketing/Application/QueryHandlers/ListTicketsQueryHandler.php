<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\QueryHandlers;

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsQueryHandler as ListTicketsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Queries\ListTicketsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use Throwable;

final class ListTicketsQueryHandler implements ListTicketsQueryHandlerPort
{
    public function __construct(
        private readonly ListTicketsUseCase $listTicketsUseCase,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    /**
     * @return array<int, Ticket>
     */
    public function execute(ListTicketsQuery $query): array
    {
        $startedAt = microtime(true);

        try {
            $result = $this->listTicketsUseCase->execute(
                new ListTicketsInputDTO(
                    page: $query->page,
                    perPage: $query->perPage,
                    status: $query->status,
                    requesterId: $query->requesterId,
                    assigneeId: $query->assigneeId,
                    search: $query->search,
                    sortBy: $query->sortBy,
                    sortDir: $query->sortDir
                )
            );
            $this->queryTelemetry->record(
                queryName: 'list_tickets',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'page' => $query->page,
                    'per_page' => $query->perPage,
                    'has_search' => $query->search === null ? 0 : 1,
                    'count' => count($result),
                ]
            );

            return $result;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'list_tickets',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'page' => $query->page,
                    'per_page' => $query->perPage,
                    'has_search' => $query->search === null ? 0 : 1,
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
