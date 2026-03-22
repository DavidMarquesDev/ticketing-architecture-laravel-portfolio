<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\QueryHandlers;

use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Queries\ListTicketCommentsQuery;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

final class ListTicketCommentsQueryHandler implements ListTicketCommentsUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketCommentRepositoryPort $ticketCommentRepository
    ) {
    }

    public function execute(ListTicketCommentsQuery $query): array
    {
        $ticket = $this->ticketRepository->findById($query->ticketId);

        if ($ticket === null) {
            throw new TicketNotFoundException('Ticket não encontrado.');
        }

        return $this->ticketCommentRepository->listByTicketId(
            ticketId: $query->ticketId,
            page: $query->page,
            perPage: $query->perPage
        );
    }
}
