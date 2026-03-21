<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketCreated;

/**
 * Serviço de aplicação para abertura de tickets.
 *
 * @author David Marques
 */
final class CreateTicketService implements CreateTicketUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly EventDispatcherPort $eventDispatcher
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

        $savedTicket = $this->ticketRepository->save($ticket);

        $this->eventDispatcher->dispatch(
            new TicketCreated(
                ticketId: $savedTicket->id(),
                requesterId: $savedTicket->requesterId()
            )
        );

        return $savedTicket;
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
