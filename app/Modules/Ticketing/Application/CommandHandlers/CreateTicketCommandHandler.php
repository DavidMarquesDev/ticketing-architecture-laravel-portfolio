<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\CommandHandlers;

use App\Modules\Ticketing\Application\Commands\CreateTicketCommand;
use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketCommandHandler as CreateTicketCommandHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use Throwable;

final class CreateTicketCommandHandler implements CreateTicketCommandHandlerPort
{
    public function __construct(
        private readonly CreateTicketUseCase $createTicketUseCase,
        private readonly QueryTelemetryPort $queryTelemetry
    ) {
    }

    public function handle(CreateTicketCommand $command): Ticket
    {
        $startedAt = microtime(true);

        try {
            $ticket = $this->createTicketUseCase->execute(
                new CreateTicketInputDTO(
                    requesterId: $command->requesterId,
                    title: $command->title,
                    description: $command->description
                )
            );
            $this->queryTelemetry->record(
                queryName: 'create_ticket',
                status: 'success',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'requester_id' => $command->requesterId,
                    'trace_id' => $command->traceId,
                    'ticket_id' => $ticket->id(),
                ]
            );

            return $ticket;
        } catch (Throwable $exception) {
            $this->queryTelemetry->record(
                queryName: 'create_ticket',
                status: 'failure',
                durationMs: $this->elapsedMilliseconds($startedAt),
                context: [
                    'requester_id' => $command->requesterId,
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
