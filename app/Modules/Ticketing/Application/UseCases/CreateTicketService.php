<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;

/**
 * Serviço de aplicação para abertura de tickets.
 *
 * @author David Marques
 */
final class CreateTicketService implements CreateTicketUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository
    ) {
    }

    public function execute(CreateTicketInputDTO $input): Ticket
    {
        $ticket = Ticket::open(
            id: self::generateId(),
            requesterId: $input->requesterId,
            title: $input->title,
            description: $input->description
        );

        return $this->ticketRepository->save($ticket);
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
