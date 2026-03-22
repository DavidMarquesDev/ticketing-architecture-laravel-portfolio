<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\ReplyTicketCommand;
use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketCommandHandler as ReplyTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use Throwable;

final class ReplyTicketCommandHandler implements ReplyTicketCommandHandlerPort
{
    public function __construct(
        private readonly ReplyTicketUseCase $replyTicketUseCase,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    public function handle(ReplyTicketCommand $command): TicketComment
    {
        $startedAt = microtime(true);

        try {
            $comment = $this->replyTicketUseCase->execute(
                new ReplyTicketInputDTO(
                    ticketId: $command->ticketId,
                    authorId: $command->authorId,
                    message: $command->message
                )
            );
            $this->queryTelemetry->record(
                queryName: 'reply_ticket',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $command->ticketId,
                    'author_id' => $command->authorId,
                    'trace_id' => $command->traceId,
                ]
            );

            return $comment;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'reply_ticket',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $command->ticketId,
                    'author_id' => $command->authorId,
                    'trace_id' => $command->traceId,
                    'exception' => $exception::class,
                ]
            );

            throw $exception;
        }
    }

    private function elapsedMilliseconds(float $startedAt): float
    {
        return (microtime(true) - $startedAt) * 1000;
    }
}
