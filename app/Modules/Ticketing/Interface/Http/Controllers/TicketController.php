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
use App\Modules\Ticketing\Infrastructure\Observability\StructuredLogger;
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

    /**
     * @return array<string, mixed>
     */
    public function store(StoreTicketRequest $request): array
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $traceId = $this->generateTraceId();

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401, $traceId);
        }

        $payload = $this->requestPayload($request);
        $requesterId = $authenticatedUser['id'] > 0
            ? $authenticatedUser['id']
            : (int) ($payload['requester_id'] ?? 0);

        $ticket = $this->createTicketCommandHandler->handle(
            new CreateTicketCommand(
                requesterId: $requesterId,
                title: (string) ($payload['title'] ?? ''),
                description: (string) ($payload['description'] ?? ''),
                traceId: $traceId
            )
        );

        http_response_code(201);

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function index(ListTicketsRequest $request): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $query = $this->queryParams($request);
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['per_page'] ?? 15);
        $tickets = $this->listTicketsQueryHandler->execute(
            new ListTicketsQuery(
                page: $page,
                perPage: $perPage,
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
            'meta' => $this->paginationMeta($page, $perPage, count($tickets)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    public function assign(AssignTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $traceId = $this->generateTraceId();

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401, $traceId);
        }

        $payload = $this->requestPayload($request);
        if (!$this->authorizeAbility($request, 'ticket.assign')) {
            return $this->errorResponse('FORBIDDEN', 'Usuário sem permissão para atribuir tickets.', 403, $traceId);
        }

        try {
            $ticket = $this->assignTicketCommandHandler->handle(
                new AssignTicketCommand(
                    ticketId: $ticketId,
                    assigneeId: (int) ($payload['assignee_id'] ?? 0),
                    actorUserId: $authenticatedUser['id'],
                    traceId: $traceId
                )
            );
        } catch (DomainException $exception) {
            return $this->errorResponse('FORBIDDEN', $exception->getMessage(), 403, $traceId);
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404, $traceId);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409, $traceId);
        }

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function close(CloseTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $traceId = $this->generateTraceId();

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401, $traceId);
        }

        if (!$this->authorizeAbility($request, 'ticket.close')) {
            return $this->errorResponse('FORBIDDEN', 'Usuário sem permissão para fechar tickets.', 403, $traceId);
        }

        try {
            $ticket = $this->closeTicketCommandHandler->handle(
                new CloseTicketCommand(
                    ticketId: $ticketId,
                    actorUserId: $authenticatedUser['id'],
                    traceId: $traceId
                )
            );
        } catch (DomainException $exception) {
            return $this->errorResponse('FORBIDDEN', $exception->getMessage(), 403, $traceId);
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404, $traceId);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409, $traceId);
        }

        return [
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reply(ReplyTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $traceId = $this->generateTraceId();

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401, $traceId);
        }

        $payload = $this->requestPayload($request);
        $authorId = $authenticatedUser['id'] > 0
            ? $authenticatedUser['id']
            : (int) ($payload['author_id'] ?? 0);
        if (!$this->authorizeAbility($request, 'ticket.reply')) {
            return $this->errorResponse('FORBIDDEN', 'Usuário sem permissão para responder tickets.', 403, $traceId);
        }

        try {
            $comment = $this->replyTicketCommandHandler->handle(
                new ReplyTicketCommand(
                    ticketId: $ticketId,
                    authorId: $authorId,
                    message: (string) ($payload['message'] ?? ''),
                    traceId: $traceId
                )
            );
        } catch (TicketNotFoundException $exception) {
            return $this->errorResponse('TICKET_NOT_FOUND', $exception->getMessage(), 404, $traceId);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('TICKET_CONFLICT', $exception->getMessage(), 409, $traceId);
        }

        http_response_code(201);

        return [
            'data' => (new TicketCommentResource($comment))->toArray($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function comments(ListTicketCommentsRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        $query = $this->queryParams($request);
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['per_page'] ?? 15);

        try {
            $comments = $this->listTicketCommentsQueryHandler->execute(
                new ListTicketCommentsQuery(
                    ticketId: $ticketId,
                    page: $page,
                    perPage: $perPage
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
            'meta' => $this->paginationMeta($page, $perPage, count($comments)),
        ];
    }

    /**
     * @return array{error: array{code: string, message: string, details: array<int, mixed>, trace_id: string}}
     */
    private function errorResponse(string $code, string $message, int $status, ?string $traceId = null): array
    {
        $traceId = $traceId ?? $this->generateTraceId();
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
        StructuredLogger::log(
            type: 'http_error',
            payload: [
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'trace_id' => $traceId,
            ]
        );
    }

    private function authorizeAbility(object $request, string $ability): bool
    {
        $gateFacade = '\Illuminate\Support\Facades\Gate';

        if (!class_exists($gateFacade) || !method_exists($gateFacade, 'forUser')) {
            return true;
        }

        $user = $this->requestUserObject($request);

        if ($user === null) {
            return false;
        }

        $gate = $gateFacade::forUser($user);

        if (!is_object($gate) || !method_exists($gate, 'allows')) {
            return true;
        }

        return (bool) $gate->allows($ability);
    }

    /**
     * @return object|array<string, mixed>|null
     */
    private function requestUserObject(object $request): object|array|null
    {
        if (!method_exists($request, 'user')) {
            return $GLOBALS['ticketing_authenticated_user'] ?? null;
        }

        $user = $request->user();

        if (is_object($user) || is_array($user)) {
            return $user;
        }

        return null;
    }

    /**
     * @return array{page: int, per_page: int, count: int, has_more: bool}
     */
    private function paginationMeta(int $page, int $perPage, int $count): array
    {
        return [
            'page' => max(1, $page),
            'per_page' => max(1, $perPage),
            'count' => max(0, $count),
            'has_more' => $count >= max(1, $perPage),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    private function queryParams(object $request): array
    {
        if (method_exists($request, 'query')) {
            $query = $request->query();

            if (is_array($query)) {
                return $query;
            }
        }

        return $_GET;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromInputStream(): array
    {
        $content = file_get_contents('php://input');

        if (!is_string($content) || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{id: int}|null
     */
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

    /**
     * @return array{id: int}|null
     */
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
