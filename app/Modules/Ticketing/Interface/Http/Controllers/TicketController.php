<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\Commands\AssignTicketCommand;
use App\Modules\Ticketing\Application\Commands\CloseTicketCommand;
use App\Modules\Ticketing\Application\Commands\CreateTicketCommand;
use App\Modules\Ticketing\Application\Commands\ReplyTicketCommand;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsQueryHandler;
use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsQueryHandler;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsQueryHandler;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketCommandHandler;
use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
use App\Modules\Ticketing\Application\Queries\ListTicketCommentsQuery;
use App\Modules\Ticketing\Application\Queries\ListTicketsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use App\Modules\Ticketing\Interface\Http\Requests\AssignTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\CloseTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ListTicketCommentsRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ListTicketsRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ReplyTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\ShowTicketRequest;
use App\Modules\Ticketing\Interface\Http\Requests\StoreTicketRequest;
use App\Modules\Ticketing\Interface\Http\Resources\TicketCommentResource;
use App\Modules\Ticketing\Interface\Http\Resources\TicketResource;
use DomainException;
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
        private readonly CreateTicketCommandHandler $createTicketCommandHandler,
        private readonly ListTicketsQueryHandler $listTicketsQueryHandler,
        private readonly GetTicketDetailsQueryHandler $getTicketDetailsQueryHandler,
        private readonly ListTicketCommentsQueryHandler $listTicketCommentsQueryHandler,
        private readonly AssignTicketCommandHandler $assignTicketCommandHandler,
        private readonly CloseTicketCommandHandler $closeTicketCommandHandler,
        private readonly ReplyTicketCommandHandler $replyTicketCommandHandler
    ) {
    }

    public function store(StoreTicketRequest $request): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $payload = $this->requestPayload($request);
        $requesterId = $authenticatedUser['id'] > 0
            ? $authenticatedUser['id']
            : (int) ($payload['requester_id'] ?? 0);

        $ticket = $this->createTicketCommandHandler->handle(
            new CreateTicketCommand(
                requesterId: $requesterId,
                title: (string) ($payload['title'] ?? ''),
                description: (string) ($payload['description'] ?? '')
            )
        );

        http_response_code(201);

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    public function index(ListTicketsRequest $request): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $query = $this->queryParams($request);
        $tickets = $this->listTicketsQueryHandler->execute(
            new ListTicketsQuery(
                page: (int) ($query['page'] ?? 1),
                perPage: (int) ($query['per_page'] ?? 15),
                status: $this->nullableString($query['status'] ?? null),
                requesterId: $this->nullableInt($query['requester_id'] ?? null),
                assigneeId: $this->nullableInt($query['assignee_id'] ?? null),
                search: $this->nullableString($query['search'] ?? null),
                sortBy: $this->sortableField($query['sort_by'] ?? null),
                sortDir: $this->sortableDirection($query['sort_dir'] ?? null)
            )
        );

        return [
            'data' => array_map(
                fn (Ticket $ticket): array => (new TicketResource($ticket))->toArray($request),
                $tickets
            ),
        ];
    }

    public function show(ShowTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        try {
            $ticket = $this->getTicketDetailsQueryHandler->execute(
                new GetTicketDetailsQuery($ticketId)
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        }

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    public function assign(AssignTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $payload = $this->requestPayload($request);

        try {
            $ticket = $this->assignTicketCommandHandler->handle(
                new AssignTicketCommand(
                    ticketId: $ticketId,
                    assigneeId: (int) ($payload['assignee_id'] ?? 0),
                    actorUserId: (int) ($authenticatedUser['id'] ?? 0)
                )
            );
        } catch (DomainException $exception) {
            return $this->errorResponse('FORBIDDEN', $exception->getMessage(), 403);
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409);
        }

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    public function close(CloseTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        try {
            $ticket = $this->closeTicketCommandHandler->handle(
                new CloseTicketCommand(
                    ticketId: $ticketId,
                    actorUserId: (int) ($authenticatedUser['id'] ?? 0)
                )
            );
        } catch (DomainException $exception) {
            return $this->errorResponse('FORBIDDEN', $exception->getMessage(), 403);
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409);
        }

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    public function reply(ReplyTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $payload = $this->requestPayload($request);
        $authorId = $authenticatedUser['id'] > 0
            ? $authenticatedUser['id']
            : (int) ($payload['author_id'] ?? 0);

        try {
            $comment = $this->replyTicketCommandHandler->handle(
                new ReplyTicketCommand(
                    ticketId: $ticketId,
                    authorId: $authorId,
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
            'data' => (new TicketCommentResource($comment))->toArray($request),
        ];
    }

    public function comments(ListTicketCommentsRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $query = $this->queryParams($request);

        try {
            $comments = $this->listTicketCommentsQueryHandler->execute(
                new ListTicketCommentsQuery(
                    ticketId: $ticketId,
                    page: (int) ($query['page'] ?? 1),
                    perPage: (int) ($query['per_page'] ?? 15)
                )
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404);
        }

        return [
            'data' => array_map(
                fn (TicketComment $comment): array => (new TicketCommentResource($comment))->toArray($request),
                $comments
            ),
        ];
    }

    private function errorResponse(string $code, string $message, int $status): array
    {
        $traceId = $this->generateTraceId();
        http_response_code($status);
        $this->logError($code, $message, $status, $traceId);

        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'trace_id' => $traceId,
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function sortableField(mixed $value): string
    {
        if (!is_string($value)) {
            return 'id';
        }

        $normalized = strtolower(trim($value));
        $allowed = ['id', 'status', 'title', 'requester_id', 'assignee_id'];

        return in_array($normalized, $allowed, true) ? $normalized : 'id';
    }

    private function sortableDirection(mixed $value): string
    {
        if (!is_string($value)) {
            return 'desc';
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : 'desc';
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function logError(string $code, string $message, int $status, string $traceId): void
    {
        error_log(
            json_encode(
                [
                    'module' => 'ticketing',
                    'type' => 'http_error',
                    'code' => $code,
                    'message' => $message,
                    'status' => $status,
                    'trace_id' => $traceId,
                ],
                JSON_UNESCAPED_UNICODE
            ) ?: ''
        );
    }

    private function requestPayload(object $request): array
    {
        if (method_exists($request, 'validated')) {
            $payload = $request->validated();

            if (is_array($payload)) {
                return $payload;
            }
        }

        if (method_exists($request, 'all')) {
            $payload = $request->all();

            if (is_array($payload) && $payload !== []) {
                return $payload;
            }
        }

        return $this->payloadFromInputStream();
    }

    private function queryParams(object $request): array
    {
        if (method_exists($request, 'query')) {
            $query = $request->query();

            if (is_array($query)) {
                return $query;
            }
        }

        return is_array($_GET) ? $_GET : [];
    }

    private function payloadFromInputStream(): array
    {
        $content = file_get_contents('php://input');

        if (!is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function authenticatedUser(object $request): ?array
    {
        if (!method_exists($request, 'user')) {
            return $this->fallbackAuthenticatedUser();
        }

        $user = $request->user();

        if ($user === null) {
            return null;
        }

        if (is_array($user)) {
            return [
                'id' => (int) ($user['id'] ?? 0),
            ];
        }

        return [
            'id' => $this->extractUserId($user),
        ];
    }

    private function extractUserId(object $user): int
    {
        if (method_exists($user, 'getAuthIdentifier')) {
            return (int) $user->getAuthIdentifier();
        }

        if (method_exists($user, 'getKey')) {
            return (int) $user->getKey();
        }

        if (property_exists($user, 'id')) {
            return (int) $user->id;
        }

        return 0;
    }

    private function fallbackAuthenticatedUser(): ?array
    {
        if (array_key_exists('ticketing_authenticated_user', $GLOBALS)) {
            $user = $GLOBALS['ticketing_authenticated_user'];

            if ($user === null) {
                return null;
            }

            if (is_array($user)) {
                return [
                    'id' => (int) ($user['id'] ?? 0),
                ];
            }
        }

        return [
            'id' => 0,
        ];
    }

}
