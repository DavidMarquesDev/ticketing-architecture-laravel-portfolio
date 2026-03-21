<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Interface\Http\Requests\StoreTicketRequest;
use App\Modules\Ticketing\Interface\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

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
        private readonly CreateTicketUseCase $createTicketUseCase
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
}
