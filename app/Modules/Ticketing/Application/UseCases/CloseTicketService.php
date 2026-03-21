<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketClosed;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

final class CloseTicketService implements CloseTicketUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketListCachePort $ticketListCache,
        private readonly DistributedLockPort $lock,
        private readonly EventDispatcherPort $eventDispatcher
    ) {
    }

    public function execute(CloseTicketInputDTO $input): Ticket
    {
        return $this->lock->execute(
            key: sprintf('ticket:close:%s', $input->ticketId),
            seconds: 5,
            callback: function () use ($input): Ticket {
                $ticket = $this->ticketRepository->findById($input->ticketId);

                if ($ticket === null) {
                    throw new TicketNotFoundException('Ticket não encontrado.');
                }

                $ticket->close();
                $savedTicket = $this->ticketRepository->save($ticket);
                $this->ticketListCache->forgetAll();
                $this->eventDispatcher->dispatch(new TicketClosed($savedTicket->id()));

                return $savedTicket;
            }
        );
    }
}
