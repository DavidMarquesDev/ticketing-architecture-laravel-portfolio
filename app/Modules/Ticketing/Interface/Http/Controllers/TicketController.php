<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use App\Modules\Ticketing\Interface\Http\Requests\AssignTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\CloseTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ListTicketsRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ReplyTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\StoreTicketRequest;
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
final class TicketController
{
    public function __construct(
        private readonly CreateTicketUseCase $createTicketUseCase,
        private readonly ListTicketsUseCase $listTicketsUseCase,
        private readonly AssignTicketUseCase $assignTicketUseCase,
        private readonly CloseTicketUseCase $closeTicketUseCase,
        private readonly ReplyTicketUseCase $replyTicketUseCase
    ) {
    }

    public function store(StoreTicketRequest $request)
    {
        $payload = $this->payload();
        $requesterId = (int) ($payload['requester_id'] ?? 0);

        $ticket = $this->createTicketUseCase->execute(
            new CreateTicketInputDTO(
                requesterId: $requesterId,
                title: (string) ($payload['title'] ?? ''),
                description: (string) ($payload['description'] ?? '')
            )
        );

        http_response_code(201);

        return [
            'data' => $this->serializeTicket($ticket),
        ];
    }

    public function index(ListTicketsRequest $request)
    {
        $tickets = $this->listTicketsUseCase->execute(
            new ListTicketsInputDTO(
                page: (int) ($_GET['page'] ?? 1),
                perPage: (int) ($_GET['per_page'] ?? 15)
            )
        );

        return [
            'data' => array_map(
                fn (Ticket $ticket): array => $this->serializeTicket($ticket),
                $tickets
            ),
        ];
    }

    public function assign(AssignTicketRequest $request, string $ticketId)
    {
        $payload = $this->payload();

        try {
            $ticket = $this->assignTicketUseCase->execute(
                new AssignTicketInputDTO(
                    ticketId: $ticketId,
                    assigneeId: (int) ($payload['assignee_id'] ?? 0)
                )
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409);
        }

        return [
            'data' => $this->serializeTicket($ticket),
        ];
    }

    public function close(CloseTicketRequest $request, string $ticketId)
    {
        try {
            $ticket = $this->closeTicketUseCase->execute(
                new CloseTicketInputDTO(ticketId: $ticketId)
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409);
        }

        return [
            'data' => $this->serializeTicket($ticket),
        ];
    }

    public function reply(ReplyTicketRequest $request, string $ticketId)
    {
        $payload = $this->payload();

        try {
            $comment = $this->replyTicketUseCase->execute(
                new ReplyTicketInputDTO(
                    ticketId: $ticketId,
                    authorId: (int) ($payload['author_id'] ?? 0),
                    message: (string) ($payload['message'] ?? '')
                )
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409);
        }

        http_response_code(201);

        return [
            'data' => $this->serializeComment($comment),
        ];
    }

    private function errorResponse(string $code, string $message, int $status)
    {
        http_response_code($status);

        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'trace_id' => '',
            ],
        ];
    }

    private function payload(): array
    {
        $content = file_get_contents('php://input');

        if (!is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function serializeTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id(),
            'requester_id' => $ticket->requesterId(),
            'assignee_id' => $ticket->assigneeId(),
            'title' => $ticket->title(),
            'description' => $ticket->description(),
            'status' => $ticket->status()->value,
        ];
    }

    private function serializeComment(TicketComment $comment): array
    {
        return [
            'id' => $comment->id(),
            'ticket_id' => $comment->ticketId(),
            'author_id' => $comment->authorId(),
            'message' => $comment->message(),
            'created_at' => $comment->createdAt(),
        ];
    }
}
