<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\CloseTicketCommand;
use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketCommandHandler as CloseTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\Ticket;

final class CloseTicketCommandHandler implements CloseTicketCommandHandlerPort
{
    public function __construct(
        private readonly CloseTicketUseCase $closeTicketUseCase
    ) {
    }

    public function handle(CloseTicketCommand $command): Ticket
    {
        return $this->closeTicketUseCase->execute(
            new CloseTicketInputDTO(
                ticketId: $command->ticketId,
                actorUserId: $command->actorUserId
            )
        );
    }
}
