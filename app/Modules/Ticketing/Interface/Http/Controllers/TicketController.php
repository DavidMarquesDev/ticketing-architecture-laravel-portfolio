<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use App\Modules\Ticketing\Interface\Http\Requests\AssignTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ListTicketsRequest;
use App\Modules\Ticketing\Interface\Http\Requests\StoreTicketRequest;
use App\Modules\Ticketing\Interface\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use RuntimeException;

/**
 * Controller responsável por receber requests de ticket.
 *
 * Exemplo de request:
 * POST /api/tickets
 * {
 *   "title": "Falha no checkout",
 *   "description": "Erro 500 ao finalizar pagamento"
 * }
 *
 * Exemplo de response:
 * HTTP 201
 * {
 *   "data": {
 *     "id": "f3b8d3a0c5f34ebf8ea6f5de84dd7f2d",
 *     "requester_id": 10,
 *     "title": "Falha no checkout",
 *     "description": "Erro 500 ao finalizar pagamento",
 *     "status": "open"
 *   }
 * }
 *
 * @author David Marques
 */
final class TicketController extends Controller
{
    public function __construct(
        private readonly CreateTicketUseCase $createTicketUseCase,
        private readonly ListTicketsUseCase $listTicketsUseCase,
        private readonly AssignTicketUseCase $assignTicketUseCase
    ) {
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->createTicketUseCase->execute(
            new CreateTicketInputDTO(
                requesterId: (int) $request->user()->id,
                title: (string) $request->string('title'),
                description: (string) $request->string('description')
            )
        );

        return (new TicketResource($ticket))
            ->response()
            ->setStatusCode(201);
    }

    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        $tickets = $this->listTicketsUseCase->execute(
            new ListTicketsInputDTO(
                page: (int) $request->integer('page', 1),
                perPage: (int) $request->integer('per_page', 15)
            )
        );

        return TicketResource::collection($tickets);
    }

    public function assign(AssignTicketRequest $request, string $ticketId): JsonResponse
    {
        try {
            $ticket = $this->assignTicketUseCase->execute(
                new AssignTicketInputDTO(
                    ticketId: $ticketId,
                    assigneeId: (int) $request->integer('assignee_id')
                )
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409);
        }

        return (new TicketResource($ticket))
            ->response()
            ->setStatusCode(200);
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'trace_id' => request()->header('X-Trace-Id', ''),
            ],
        ], $status);
    }
}
