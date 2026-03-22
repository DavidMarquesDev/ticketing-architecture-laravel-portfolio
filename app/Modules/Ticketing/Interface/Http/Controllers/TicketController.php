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
use App\Modules\Ticketing\Interface\Http\Policies\TicketPolicy;
use App\Modules\Ticketing\Interface\Http\Resources\TicketCommentResource;
use App\Modules\Ticketing\Interface\Http\Resources\TicketResource;
use App\Modules\Ticketing\Infrastructure\Observability\StructuredLogger;
use DomainException;
use Illuminate\Http\JsonResponse;
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
 * @tags Tickets
 *
 * @author David Marques
 */
final class TicketController
{
    /**
     * Construtor do controller de tickets.
     *
     * @param CreateTicketCommandHandler $createTicketCommandHandler Handler para criação de ticket.
     * @param ListTicketsQueryHandler $listTicketsQueryHandler Handler para listagem paginada de tickets.
     * @param GetTicketDetailsQueryHandler $getTicketDetailsQueryHandler Handler para detalhamento de ticket.
     * @param ListTicketCommentsQueryHandler $listTicketCommentsQueryHandler Handler para listagem de comentários.
     * @param AssignTicketCommandHandler $assignTicketCommandHandler Handler para atribuição de ticket.
     * @param CloseTicketCommandHandler $closeTicketCommandHandler Handler para fechamento de ticket.
     * @param ReplyTicketCommandHandler $replyTicketCommandHandler Handler para resposta de ticket.
     *
     * @author David Marques
     */
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
     * Cria um novo ticket para o usuário autenticado.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` com os dados do ticket criado.
     *
     * @param StoreTicketRequest $request Requisição validada com título e descrição.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * POST /api/tickets
     * {
     *   "title": "Falha no checkout",
     *   "description": "Erro 500 ao finalizar pagamento"
     * }
     *
     * @example Response 201
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
    public function store(StoreTicketRequest $request): array|JsonResponse
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

        return $this->responsePayload([
            'data' => (new TicketResource($ticket))->toArray($request),
        ], 201);
    }

    /**
     * Lista tickets com paginação e filtros opcionais.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` com coleção de tickets.
     * - `meta` com paginação (`page`, `per_page`, `count`, `has_more`).
     *
     * @param ListTicketsRequest $request Requisição com filtros e paginação.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * GET /api/tickets?page=1&per_page=15&status=open&sort_by=id&sort_dir=desc
     *
     * @example Response 200
     * {
     *   "data": [],
     *   "meta": {
     *     "page": 1,
     *     "per_page": 15,
     *     "count": 0,
     *     "has_more": false
     *   }
     * }
     *
     * @author David Marques
     */
    public function index(ListTicketsRequest $request): array|JsonResponse
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

        return $this->responsePayload([
            'data' => array_map(
                fn (Ticket $ticket): array => (new TicketResource($ticket))->toArray($request),
                $tickets
            ),
            'meta' => $this->paginationMeta($page, $perPage, count($tickets)),
        ]);
    }

    /**
     * Retorna detalhes de um ticket pelo identificador.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` em sucesso.
     * - `error` com código `TICKET_NOT_FOUND` quando não encontrado.
     *
     * @param ShowTicketRequest $request Requisição autenticada.
     * @param string $ticketId Identificador do ticket.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * GET /api/tickets/{ticketId}
     *
     * @example Response 200
     * {
     *   "data": {
     *     "id": "f3b8d3a0c5f34ebf8ea6f5de84dd7f2d"
     *   }
     * }
     *
     * @example Response 404
     * {
     *   "error": {
     *     "code": "TICKET_NOT_FOUND",
     *     "message": "Ticket não encontrado.",
     *     "details": [],
     *     "trace_id": "abc123def4567890"
     *   }
     * }
     *
     * @author David Marques
     */
    public function show(ShowTicketRequest $request, string $ticketId): array|JsonResponse
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

        return $this->responsePayload([
            'data' => (new TicketResource($ticket))->toArray($request),
        ]);
    }

    /**
     * Atribui um ticket para um responsável.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     * - ✅ Requer permissão `ticket.assign`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` em sucesso.
     * - `error` com códigos `FORBIDDEN`, `TICKET_NOT_FOUND` ou `TICKET_CONFLICT`.
     *
     * @param AssignTicketRequest $request Requisição com assignee_id.
     * @param string $ticketId Identificador do ticket.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * PATCH /api/tickets/{ticketId}/assign
     * {
     *   "assignee_id": 20
     * }
     *
     * @example Response 200
     * {
     *   "data": {
     *     "id": "f3b8d3a0c5f34ebf8ea6f5de84dd7f2d"
     *   }
     * }
     *
     * @author David Marques
     */
    public function assign(AssignTicketRequest $request, string $ticketId): array|JsonResponse
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

        return $this->responsePayload([
            'data' => (new TicketResource($ticket))->toArray($request),
        ]);
    }

    /**
     * Fecha um ticket em aberto.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     * - ✅ Requer permissão `ticket.close`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` em sucesso.
     * - `error` com códigos de domínio em falha.
     *
     * @param CloseTicketRequest $request Requisição autenticada.
     * @param string $ticketId Identificador do ticket.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * PATCH /api/tickets/{ticketId}/close
     *
     * @example Response 200
     * {
     *   "data": {
     *     "id": "f3b8d3a0c5f34ebf8ea6f5de84dd7f2d",
     *     "status": "closed"
     *   }
     * }
     *
     * @author David Marques
     */
    public function close(CloseTicketRequest $request, string $ticketId): array|JsonResponse
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

        return $this->responsePayload([
            'data' => (new TicketResource($ticket))->toArray($request),
        ]);
    }

    /**
     * Adiciona uma resposta ao ticket.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     * - ✅ Requer permissão `ticket.reply`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` com comentário criado.
     * - `error` com códigos `TICKET_NOT_FOUND` ou `TICKET_CONFLICT`.
     *
     * @param ReplyTicketRequest $request Requisição com mensagem de resposta.
     * @param string $ticketId Identificador do ticket.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * POST /api/tickets/{ticketId}/reply
     * {
     *   "message": "Conseguimos reproduzir o problema e estamos atuando."
     * }
     *
     * @example Response 201
     * {
     *   "data": {
     *     "ticket_id": "f3b8d3a0c5f34ebf8ea6f5de84dd7f2d",
     *     "message": "Conseguimos reproduzir o problema e estamos atuando."
     *   }
     * }
     *
     * @author David Marques
     */
    public function reply(ReplyTicketRequest $request, string $ticketId): array|JsonResponse
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

        return $this->responsePayload([
            'data' => (new TicketCommentResource($comment))->toArray($request),
        ], 201);
    }

    /**
     * Lista comentários de um ticket com paginação.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via `Sanctum`.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` com comentários.
     * - `meta` com dados de paginação.
     *
     * @param ListTicketCommentsRequest $request Requisição com parâmetros de paginação.
     * @param string $ticketId Identificador do ticket.
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * GET /api/tickets/{ticketId}/comments?page=1&per_page=15
     *
     * @example Response 200
     * {
     *   "data": [],
     *   "meta": {
     *     "page": 1,
     *     "per_page": 15,
     *     "count": 0,
     *     "has_more": false
     *   }
     * }
     *
     * @author David Marques
     */
    public function comments(ListTicketCommentsRequest $request, string $ticketId): array|JsonResponse
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

        return $this->responsePayload([
            'data' => array_map(
                fn (TicketComment $comment): array => (new TicketCommentResource($comment))->toArray($request),
                $comments
            ),
            'meta' => $this->paginationMeta($page, $perPage, count($comments)),
        ]);
    }

    /**
     * Retorna payload padronizado de erro da API.
     *
     * @param string $code Código de erro interno.
     * @param string $message Mensagem para consumo do frontend.
     * @param int $status Status HTTP.
     * @param string|null $traceId Identificador de rastreio da requisição.
     * @return array<string, mixed>|JsonResponse
     *
     * @author David Marques
     */
    private function errorResponse(string $code, string $message, int $status, ?string $traceId = null): array|JsonResponse
    {
        $traceId = $traceId ?? $this->generateTraceId();
        $this->logError($code, $message, $status, $traceId);

        return $this->responsePayload([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'trace_id' => $traceId,
            ],
        ], $status);
    }

    /**
     * Normaliza retorno para array em testes e JsonResponse em runtime.
     *
     * @param array<string, mixed> $payload
     * @param int $status Código HTTP da resposta.
     * @return array<string, mixed>|JsonResponse
     *
     * @author David Marques
     */
    private function responsePayload(array $payload, int $status = 200): array|JsonResponse
    {
        if (function_exists('response') && class_exists(JsonResponse::class)) {
            return response()->json($payload, $status);
        }

        http_response_code($status);

        return $payload;
    }

    /**
     * Converte valor escalar para string normalizada opcional.
     *
     * @param mixed $value Valor bruto recebido nos filtros da query.
     * @return string|null
     *
     * @author David Marques
     */
    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Converte valor escalar para inteiro positivo opcional.
     *
     * @param mixed $value Valor bruto recebido nos filtros da query.
     * @return int|null
     *
     * @author David Marques
     */
    private function nullableInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    /**
     * Normaliza campo de ordenação para lista permitida.
     *
     * @param mixed $value Campo solicitado para ordenação.
     * @return string
     *
     * @author David Marques
     */
    private function sortableField(mixed $value): string
    {
        if (!is_string($value)) {
            return 'id';
        }

        $normalized = strtolower(trim($value));
        $allowed = ['id', 'status', 'title', 'requester_id', 'assignee_id'];

        return in_array($normalized, $allowed, true) ? $normalized : 'id';
    }

    /**
     * Normaliza direção de ordenação para valores válidos.
     *
     * @param mixed $value Direção solicitada.
     * @return string
     *
     * @author David Marques
     */
    private function sortableDirection(mixed $value): string
    {
        if (!is_string($value)) {
            return 'desc';
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : 'desc';
    }

    /**
     * Gera identificador hexadecimal para rastreio de erros.
     *
     * @return string
     *
     * @author David Marques
     */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Registra erro HTTP estruturado para observabilidade.
     *
     * @param string $code Código interno do erro.
     * @param string $message Mensagem de erro.
     * @param int $status Status HTTP correspondente.
     * @param string $traceId Identificador de rastreio.
     * @return void
     *
     * @author David Marques
     */
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

    /**
     * Verifica autorização por policy e fallback para Gate.
     *
     * @param object $request Requisição atual.
     * @param string $ability Habilidade a ser validada.
     * @return bool
     *
     * @author David Marques
     */
    private function authorizeAbility(object $request, string $ability): bool
    {
        $user = $this->requestUserObject($request);

        if ($user === null) {
            return false;
        }

        $policyAuthorization = $this->authorizeUsingPolicy($user, $ability);

        if ($policyAuthorization) {
            return true;
        }

        $gateFacade = '\Illuminate\Support\Facades\Gate';

        if (!class_exists($gateFacade)) {
            return $policyAuthorization;
        }

        try {
            $gate = $gateFacade::forUser($user);
        } catch (\Throwable) {
            return $policyAuthorization;
        }

        if (!is_object($gate) || !method_exists($gate, 'allows')) {
            return $policyAuthorization;
        }

        return (bool) $gate->allows($ability);
    }

    /**
     * Resolve autorização via TicketPolicy para habilidade informada.
     *
     * @param object|array<string, mixed> $user
     * @param string $ability Habilidade alvo.
     * @return bool
     *
     * @author David Marques
     */
    private function authorizeUsingPolicy(object|array $user, string $ability): bool
    {
        $policy = new TicketPolicy();

        return match ($ability) {
            'ticket.assign' => $policy->assign($user),
            'ticket.close' => $policy->close($user),
            'ticket.reply' => $policy->reply($user),
            default => true,
        };
    }

    /**
     * Obtém usuário autenticado em formato objeto ou array.
     *
     * @param object $request Requisição atual.
     * @return object|array<string, mixed>|null
     *
     * @author David Marques
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
     * Monta metadados de paginação para respostas de listagem.
     *
     * @param int $page Página atual solicitada.
     * @param int $perPage Quantidade por página.
     * @param int $count Quantidade de itens retornados.
     * @return array{page: int, per_page: int, count: int, has_more: bool}
     *
     * @author David Marques
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
     * Extrai payload validado da requisição com fallback para body bruto.
     *
     * @param object $request Requisição HTTP atual.
     * @return array<string, mixed>
     *
     * @author David Marques
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
     * Extrai parâmetros de query string com fallback para $_GET.
     *
     * @param object $request Requisição HTTP atual.
     * @return array<string, mixed>
     *
     * @author David Marques
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
     * Lê e decodifica JSON bruto do input stream.
     *
     * @return array<string, mixed>
     *
     * @author David Marques
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
     * Resolve usuário autenticado para estrutura mínima de identificação.
     *
     * @param object $request Requisição HTTP atual.
     * @return array{id: int}|null
     *
     * @author David Marques
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

    /**
     * Extrai identificador do usuário autenticado.
     *
     * @param object $user Entidade de usuário autenticado.
     * @return int
     *
     * @author David Marques
     */
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
     * Obtém usuário autenticado de fallback para execução sem framework completo.
     *
     * @return array{id: int}|null
     *
     * @author David Marques
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
