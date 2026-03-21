<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

final class AssignTicketService implements AssignTicketUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketListCachePort $ticketListCache,
        private readonly DistributedLockPort $lock
    ) {
    }

    public function execute(AssignTicketInputDTO $input): Ticket
    {
        return $this->lock->execute(
            key: sprintf('ticket:assign:%s', $input->ticketId),
            seconds: 5,
            callback: function () use ($input): Ticket {
                $ticket = $this->ticketRepository->findById($input->ticketId);

                if ($ticket === null) {
                    throw new TicketNotFoundException('Ticket não encontrado.');
                }

                $ticket->assignTo($input->assigneeId);
                $savedTicket = $this->ticketRepository->save($ticket);

                $this->ticketListCache->forgetAll();

                return $savedTicket;
            }
        );
    }
}
