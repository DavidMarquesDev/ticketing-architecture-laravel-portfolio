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
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
use App\Modules\Ticketing\Application\Queries\ListTicketCommentsQuery;
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
        private readonly GetTicketDetailsUseCase $getTicketDetailsUseCase,
        private readonly ListTicketCommentsUseCase $listTicketCommentsUseCase,
        private readonly AssignTicketUseCase $assignTicketUseCase,
        private readonly CloseTicketUseCase $closeTicketUseCase,
        private readonly ReplyTicketUseCase $replyTicketUseCase
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

        $ticket = $this->createTicketUseCase->execute(
            new CreateTicketInputDTO(
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
        $tickets = $this->listTicketsUseCase->execute(
            new ListTicketsInputDTO(
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
            $ticket = $this->getTicketDetailsUseCase->execute(
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

        if (!$this->hasAnyRole($authenticatedUser, ['admin', 'agent'])) {
            return $this->errorResponse('FORBIDDEN', 'Usuário sem permissão para atribuir tickets.', 403);
        }

        $payload = $this->requestPayload($request);

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
            'data' => (new TicketResource($ticket))->toArray($request),
        ];
    }

    public function close(CloseTicketRequest $request, string $ticketId): array
    {
        $authenticatedUser = $this->authenticatedUser($request);

        if ($authenticatedUser === null) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401);
        }

        if (!$this->hasAnyRole($authenticatedUser, ['admin', 'agent'])) {
            return $this->errorResponse('FORBIDDEN', 'Usuário sem permissão para fechar tickets.', 403);
        }

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
            $comment = $this->replyTicketUseCase->execute(
                new ReplyTicketInputDTO(
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
            $comments = $this->listTicketCommentsUseCase->execute(
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
            $id = (int) ($user['id'] ?? 0);
            $roles = $this->normalizeRoles($user['roles'] ?? []);

            return [
                'id' => $id,
                'roles' => $roles,
                'subject' => $user,
            ];
        }

        $id = $this->extractUserId($user);
        $roles = $this->extractUserRoles($user);

        return [
            'id' => $id,
            'roles' => $roles,
            'subject' => $user,
        ];
    }

    private function hasAnyRole(array $authenticatedUser, array $allowedRoles): bool
    {
        $normalizedAllowedRoles = array_map('strtolower', $allowedRoles);
        $userRoles = array_map('strtolower', $authenticatedUser['roles'] ?? []);

        if (array_intersect($userRoles, $normalizedAllowedRoles) !== []) {
            return true;
        }

        $subject = $authenticatedUser['subject'] ?? null;

        if (is_object($subject) && method_exists($subject, 'hasAnyRole')) {
            return (bool) $subject->hasAnyRole($allowedRoles);
        }

        if (is_object($subject) && method_exists($subject, 'hasRole')) {
            foreach ($allowedRoles as $allowedRole) {
                if ((bool) $subject->hasRole($allowedRole)) {
                    return true;
                }
            }
        }

        return false;
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

    private function extractUserRoles(object $user): array
    {
        if (method_exists($user, 'roles')) {
            return $this->normalizeRoles($user->roles());
        }

        if (method_exists($user, 'getRoleNames')) {
            return $this->normalizeRoles($user->getRoleNames());
        }

        if (property_exists($user, 'roles')) {
            return $this->normalizeRoles($user->roles);
        }

        return [];
    }

    private function normalizeRoles(mixed $roles): array
    {
        if (!is_iterable($roles) && !is_array($roles)) {
            return [];
        }

        $normalized = [];

        foreach ($roles as $role) {
            if (is_string($role) && $role !== '') {
                $normalized[] = strtolower($role);
                continue;
            }

            if (is_object($role) && property_exists($role, 'name') && is_string($role->name) && $role->name !== '') {
                $normalized[] = strtolower($role->name);
            }
        }

        return array_values(array_unique($normalized));
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
                    'roles' => $this->normalizeRoles($user['roles'] ?? []),
                    'subject' => $user,
                ];
            }
        }

        return [
            'id' => 0,
            'roles' => ['admin'],
            'subject' => null,
        ];
    }

}
