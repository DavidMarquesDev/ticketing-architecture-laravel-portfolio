<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;

final class ListTicketsService implements ListTicketsUseCase
{
    private const CACHE_TTL_SECONDS = 120;

    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketListCachePort $ticketListCache
    ) {
    }

    public function execute(ListTicketsInputDTO $input): array
    {
        $cachedTickets = $this->ticketListCache->get(
            page: $input->page,
            perPage: $input->perPage,
            status: $input->status,
            requesterId: $input->requesterId,
            assigneeId: $input->assigneeId,
            search: $input->search,
            sortBy: $input->sortBy,
            sortDir: $input->sortDir
        );

        if ($cachedTickets !== null) {
            return $cachedTickets;
        }

        $tickets = $this->ticketRepository->list(
            page: $input->page,
            perPage: $input->perPage,
            status: $input->status,
            requesterId: $input->requesterId,
            assigneeId: $input->assigneeId,
            search: $input->search,
            sortBy: $input->sortBy,
            sortDir: $input->sortDir
        );
        $this->ticketListCache->put(
            page: $input->page,
            perPage: $input->perPage,
            tickets: $tickets,
            seconds: self::CACHE_TTL_SECONDS,
            status: $input->status,
            requesterId: $input->requesterId,
            assigneeId: $input->assigneeId,
            search: $input->search,
            sortBy: $input->sortBy,
            sortDir: $input->sortDir
        );

        return $tickets;
    }
}
