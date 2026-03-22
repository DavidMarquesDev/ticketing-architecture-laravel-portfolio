<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\CloseTicketCommand;
use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketCommandHandler as CloseTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use Throwable;

final class CloseTicketCommandHandler implements CloseTicketCommandHandlerPort
{
    public function __construct(
        private readonly CloseTicketUseCase $closeTicketUseCase,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    public function handle(CloseTicketCommand $command): Ticket
    {
        $startedAt = microtime(true);

        try {
            $ticket = $this->closeTicketUseCase->execute(
                new CloseTicketInputDTO(
                    ticketId: $command->ticketId,
                    actorUserId: $command->actorUserId
                )
            );
            $this->queryTelemetry->record(
                queryName: 'close_ticket',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $command->ticketId,
                    'actor_user_id' => $command->actorUserId,
                    'trace_id' => $command->traceId,
                ]
            );

            return $ticket;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'close_ticket',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $command->ticketId,
                    'actor_user_id' => $command->actorUserId,
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
