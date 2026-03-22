<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\CreateTicketCommand;
use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketCommandHandler as CreateTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\Ticket;

final class CreateTicketCommandHandler implements CreateTicketCommandHandlerPort
{
    public function __construct(
        private readonly CreateTicketUseCase $createTicketUseCase
    ) {
    }

    public function handle(CreateTicketCommand $command): Ticket
    {
        return $this->createTicketUseCase->execute(
            new CreateTicketInputDTO(
                requesterId: $command->requesterId,
                title: $command->title,
                description: $command->description
            )
        );
    }
}
