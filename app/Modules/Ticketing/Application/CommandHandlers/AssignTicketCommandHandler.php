<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\AssignTicketCommand;
use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketCommandHandler as AssignTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use Throwable;

final class AssignTicketCommandHandler implements AssignTicketCommandHandlerPort
{
    public function __construct(
        private readonly AssignTicketUseCase $assignTicketUseCase,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    public function handle(AssignTicketCommand $command): Ticket
    {
        $startedAt = microtime(true);

        try {
            $ticket = $this->assignTicketUseCase->execute(
                new AssignTicketInputDTO(
                    ticketId: $command->ticketId,
                    assigneeId: $command->assigneeId,
                    actorUserId: $command->actorUserId
                )
            );
            $this->queryTelemetry->record(
                queryName: 'assign_ticket',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $command->ticketId,
                    'assignee_id' => $command->assigneeId,
                    'actor_user_id' => $command->actorUserId,
                    'trace_id' => $command->traceId,
                ]
            );

            return $ticket;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'assign_ticket',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'ticket_id' => $command->ticketId,
                    'assignee_id' => $command->assigneeId,
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
