<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\QueryHandlers;

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsQueryHandler as ListTicketsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Queries\ListTicketsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;

final class ListTicketsQueryHandler implements ListTicketsQueryHandlerPort
{
    public function __construct(
        private readonly ListTicketsUseCase $listTicketsUseCase
    ) {
    }

    /**
     * @return array<int, Ticket>
     */
    public function execute(ListTicketsQuery $query): array
    {
        return $this->listTicketsUseCase->execute(
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
    }
}
