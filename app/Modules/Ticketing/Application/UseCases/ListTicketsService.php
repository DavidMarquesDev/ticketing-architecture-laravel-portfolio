<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;

final class ListTicketsService implements ListTicketsUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketListCachePort $ticketListCache
    ) {
    }

    public function execute(ListTicketsInputDTO $input): array
    {
        $cachedTickets = $this->ticketListCache->get($input->page, $input->perPage);

        if ($cachedTickets !== null) {
            return $cachedTickets;
        }

        $tickets = $this->ticketRepository->list($input->page, $input->perPage);
        $this->ticketListCache->put($input->page, $input->perPage, $tickets, 120);

        return $tickets;
    }
}
