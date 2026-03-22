<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\AssignTicketCommand;
use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketCommandHandler as AssignTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\Ticket;

final class AssignTicketCommandHandler implements AssignTicketCommandHandlerPort
{
    public function __construct(
        private readonly AssignTicketUseCase $assignTicketUseCase
    ) {
    }

    public function handle(AssignTicketCommand $command): Ticket
    {
        return $this->assignTicketUseCase->execute(
            new AssignTicketInputDTO(
                ticketId: $command->ticketId,
                assigneeId: $command->assigneeId,
                actorUserId: $command->actorUserId
            )
        );
    }
}
