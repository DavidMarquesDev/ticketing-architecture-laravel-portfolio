<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\ReplyTicketCommand;
use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketCommandHandler as ReplyTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\TicketComment;

final class ReplyTicketCommandHandler implements ReplyTicketCommandHandlerPort
{
    public function __construct(
        private readonly ReplyTicketUseCase $replyTicketUseCase
    ) {
    }

    public function handle(ReplyTicketCommand $command): TicketComment
    {
        return $this->replyTicketUseCase->execute(
            new ReplyTicketInputDTO(
                ticketId: $command->ticketId,
                authorId: $command->authorId,
                message: $command->message
            )
        );
    }
}
