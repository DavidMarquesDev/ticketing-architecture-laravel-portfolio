<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\QueryHandlers;

use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsQueryHandler as GetTicketDetailsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

final class GetTicketDetailsQueryHandler implements GetTicketDetailsUseCase, GetTicketDetailsQueryHandlerPort
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository
    ) {
    }

    public function execute(GetTicketDetailsQuery $query): Ticket
    {
        $ticket = $this->ticketRepository->findById($query->ticketId);

        if ($ticket === null) {
            throw new TicketNotFoundException('Ticket não encontrado.');
        }

        return $ticket;
    }
}
