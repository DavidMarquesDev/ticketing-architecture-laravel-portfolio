<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Http {

    if (!class_exists(FormRequest::class)) {
        class FormRequest
        {
        }
    }
}

namespace App\Modules\Ticketing\Interface\Http\Controllers {

    if (!function_exists(__NAMESPACE__ . '\file_get_contents')) {
        function file_get_contents(string $filename): string|false
        {
            if ($filename !== 'php://input') {
                return \file_get_contents($filename);
            }

            return (string) ($GLOBALS['ticketing_http_payload'] ?? '');
        }
    }

    if (!function_exists(__NAMESPACE__ . '\http_response_code')) {
        function http_response_code(?int $code = null): int|bool
        {
            if ($code !== null) {
                $GLOBALS['ticketing_http_status_code'] = $code;

                return true;
            }

            return (int) ($GLOBALS['ticketing_http_status_code'] ?? 200);
        }
    }
}

namespace {

    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });

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
    use App\Modules\Ticketing\Interface\Http\Controllers\TicketController;
    use App\Modules\Ticketing\Interface\Http\Requests\AssignTicketRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\CloseTicketRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\ListTicketCommentsRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\ListTicketsRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\ReplyTicketRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\ShowTicketRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\StoreTicketRequest;

    $tests = [
        'ticket_controller_store_returns_201_and_data_contract' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_http_payload'] = json_encode([
                'requester_id' => 10,
                'title' => 'Falha no checkout',
                'description' => 'Erro 500 ao finalizar pagamento',
            ], JSON_THROW_ON_ERROR);

            $controller = new TicketController(
                new class implements CreateTicketCommandHandler {
                    public function handle(CreateTicketCommand $command): Ticket
                    {
                        $GLOBALS['captured_create_command'] = $command;

                        return Ticket::open('t-http-1', $command->requesterId, $command->title, $command->description);
                    }
                },
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->store(new StoreTicketRequest());
            $command = $GLOBALS['captured_create_command'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Store deve responder HTTP 201.');
            assertSame(10, $command->requesterId, 'Command de criação deve mapear requester_id.');
            assertSame('Falha no checkout', $command->title, 'Command de criação deve mapear title.');
            assertSame('Erro 500 ao finalizar pagamento', $command->description, 'Command de criação deve mapear description.');
            assertSame('t-http-1', $response['data']['id'], 'Response deve retornar id do ticket.');
            assertSame('open', $response['data']['status'], 'Response deve retornar status do ticket.');
        },
        'ticket_controller_store_maps_authenticated_user_to_requester_id' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 55, 'roles' => ['customer']];

            $controller = new TicketController(
                new class implements CreateTicketCommandHandler {
                    public function handle(CreateTicketCommand $command): Ticket
                    {
                        $GLOBALS['captured_create_command'] = $command;

                        return Ticket::open('t-http-auth-1', $command->requesterId, $command->title, $command->description);
                    }
                },
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $GLOBALS['ticketing_http_payload'] = json_encode([
                'requester_id' => 10,
                'title' => 'Falha no checkout',
                'description' => 'Erro 500 ao finalizar pagamento',
            ], JSON_THROW_ON_ERROR);

            $response = $controller->store(new StoreTicketRequest());

            $command = $GLOBALS['captured_create_command'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Store deve responder HTTP 201.');
            assertSame(55, $command->requesterId, 'Store deve usar o usuário autenticado como requester.');
            assertSame('t-http-auth-1', $response['data']['id'], 'Response deve retornar id serializado.');
        },
        'ticket_controller_store_returns_401_when_user_is_not_authenticated' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = null;
            $GLOBALS['create_handler_called'] = false;

            $controller = new TicketController(
                new class implements CreateTicketCommandHandler {
                    public function handle(CreateTicketCommand $command): Ticket
                    {
                        $GLOBALS['create_handler_called'] = true;

                        return Ticket::open('t-http-unauth', $command->requesterId, $command->title, $command->description);
                    }
                },
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $GLOBALS['ticketing_http_payload'] = json_encode([
                'requester_id' => 10,
                'title' => 'Falha no checkout',
                'description' => 'Erro 500 ao finalizar pagamento',
            ], JSON_THROW_ON_ERROR);

            $response = $controller->store(new StoreTicketRequest());

            assertSame(401, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Store deve responder 401 sem usuário autenticado.');
            assertSame('UNAUTHENTICATED', $response['error']['code'], 'Store deve retornar código UNAUTHENTICATED.');
            assertSame(false, $GLOBALS['create_handler_called'], 'Store não deve executar command handler sem autenticação.');
        },
        'ticket_controller_index_reads_query_and_returns_list_contract' => static function (): void {
            resetHttpContext();
            $_GET = [
                'page' => '2',
                'per_page' => '1',
                'status' => 'pending',
                'requester_id' => '20',
                'assignee_id' => '77',
                'search' => 'checkout',
                'sort_by' => 'title',
                'sort_dir' => 'asc',
            ];

            $controller = new TicketController(
                noopCreateUseCase(),
                new class implements ListTicketsQueryHandler {
                    public function execute(ListTicketsQuery $query): array
                    {
                        $GLOBALS['captured_list_query'] = $query;

                        return [Ticket::open('t-http-2', 20, 'Título 2', 'Descrição 2')];
                    }
                },
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->index(new ListTicketsRequest());
            $query = $GLOBALS['captured_list_query'];

            assertSame(2, $query->page, 'Index deve mapear page para Query.');
            assertSame(1, $query->perPage, 'Index deve mapear per_page para Query.');
            assertSame('pending', $query->status, 'Index deve mapear status para Query.');
            assertSame(20, $query->requesterId, 'Index deve mapear requester_id para Query.');
            assertSame(77, $query->assigneeId, 'Index deve mapear assignee_id para Query.');
            assertSame('checkout', $query->search, 'Index deve mapear search para Query.');
            assertSame('title', $query->sortBy, 'Index deve mapear sort_by para Query.');
            assertSame('asc', $query->sortDir, 'Index deve mapear sort_dir para Query.');
            assertSame('t-http-2', $response['data'][0]['id'], 'Index deve serializar id.');
            assertSame('Título 2', $response['data'][0]['title'], 'Index deve serializar título.');
        },
        'ticket_controller_show_returns_200_and_data_contract' => static function (): void {
            resetHttpContext();

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                new class implements GetTicketDetailsQueryHandler {
                    public function execute(GetTicketDetailsQuery $query): Ticket
                    {
                        $GLOBALS['captured_show_query'] = $query;

                        return Ticket::open($query->ticketId, 20, 'Título detalhado', 'Descrição detalhada');
                    }
                },
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->show(new ShowTicketRequest(), 't-http-show-1');
            $query = $GLOBALS['captured_show_query'];

            assertSame('t-http-show-1', $query->ticketId, 'Show deve mapear ticketId para Query.');
            assertSame('t-http-show-1', $response['data']['id'], 'Show deve serializar id.');
            assertSame('Título detalhado', $response['data']['title'], 'Show deve serializar título.');
        },
        'ticket_controller_show_maps_not_found_to_404' => static function (): void {
            resetHttpContext();

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                new class implements GetTicketDetailsQueryHandler {
                    public function execute(GetTicketDetailsQuery $query): Ticket
                    {
                        throw new TicketNotFoundException('Ticket não encontrado.');
                    }
                },
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->show(new ShowTicketRequest(), 't-http-show-404');

            assertSame(404, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Show deve mapear TicketNotFoundException para 404.');
            assertSame('TICKET_NOT_FOUND', $response['error']['code'], 'Show deve retornar código TICKET_NOT_FOUND.');
        },
        'ticket_controller_assign_maps_not_found_to_404' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 99, 'roles' => ['admin']];
            $GLOBALS['ticketing_http_payload'] = json_encode(['assignee_id' => 77], JSON_THROW_ON_ERROR);

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                new class implements AssignTicketCommandHandler {
                    public function handle(AssignTicketCommand $command): Ticket
                    {
                        $GLOBALS['captured_assign_command'] = $command;

                        throw new TicketNotFoundException('Ticket não encontrado.');
                    }
                },
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->assign(new AssignTicketRequest(), 'ticket-inexistente');
            $input = $GLOBALS['captured_assign_command'];

            assertSame(404, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Assign deve mapear TicketNotFoundException para 404.');
            assertSame('TICKET_NOT_FOUND', $response['error']['code'], 'Payload de erro deve usar código padronizado.');
            assertSame(99, $input->actorUserId, 'Assign deve enviar usuário autenticado no Command de atribuição.');
        },
        'ticket_controller_assign_returns_403_when_user_has_no_permission' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 33, 'roles' => ['customer']];
            $GLOBALS['assign_handler_called'] = false;

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                new class implements AssignTicketCommandHandler {
                    public function handle(AssignTicketCommand $command): Ticket
                    {
                        $GLOBALS['assign_handler_called'] = true;

                        throw new \DomainException('Usuário sem permissão para atribuir tickets.');
                    }
                },
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $GLOBALS['ticketing_http_payload'] = json_encode(['assignee_id' => 77], JSON_THROW_ON_ERROR);

            $response = $controller->assign(new AssignTicketRequest(), 'ticket-forbidden');

            assertSame(403, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Assign deve responder 403 sem permissão.');
            assertSame('FORBIDDEN', $response['error']['code'], 'Assign deve retornar código FORBIDDEN.');
            assertSame(true, $GLOBALS['assign_handler_called'], 'Assign deve mapear retorno de autorização do command handler.');
        },
        'ticket_controller_close_maps_conflict_to_409' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 44, 'roles' => ['agent']];

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                new class implements CloseTicketCommandHandler {
                    public function handle(CloseTicketCommand $command): Ticket
                    {
                        $GLOBALS['captured_close_command'] = $command;

                        throw new \RuntimeException('Ticket já está fechado.');
                    }
                },
                noopReplyUseCase()
            );

            $response = $controller->close(new CloseTicketRequest(), 't-closed');
            $input = $GLOBALS['captured_close_command'];

            assertSame(409, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Close deve mapear conflito para 409.');
            assertSame('TICKET_CONFLICT', $response['error']['code'], 'Payload de erro deve usar TICKET_CONFLICT.');
            assertSame(44, $input->actorUserId, 'Close deve enviar usuário autenticado no Command de fechamento.');
        },
        'ticket_controller_close_returns_403_when_user_has_no_permission' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 33, 'roles' => ['customer']];
            $GLOBALS['close_handler_called'] = false;

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                new class implements CloseTicketCommandHandler {
                    public function handle(CloseTicketCommand $command): Ticket
                    {
                        $GLOBALS['close_handler_called'] = true;

                        throw new \DomainException('Usuário sem permissão para fechar tickets.');
                    }
                },
                noopReplyUseCase()
            );

            $response = $controller->close(new CloseTicketRequest(), 'ticket-forbidden');

            assertSame(403, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Close deve responder 403 sem permissão.');
            assertSame('FORBIDDEN', $response['error']['code'], 'Close deve retornar código FORBIDDEN.');
            assertSame(true, $GLOBALS['close_handler_called'], 'Close deve mapear retorno de autorização do command handler.');
        },
        'ticket_controller_reply_returns_201_and_comment_contract' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_http_payload'] = json_encode([
                'author_id' => 99,
                'message' => 'Aplicada correção.',
            ], JSON_THROW_ON_ERROR);

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopListTicketCommentsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                new class implements ReplyTicketCommandHandler {
                    public function handle(ReplyTicketCommand $command): TicketComment
                    {
                        $GLOBALS['captured_reply_command'] = $command;

                        return TicketComment::create('c-http-1', $command->ticketId, $command->authorId, $command->message);
                    }
                }
            );

            $response = $controller->reply(new ReplyTicketRequest(), 't-http-1');
            $command = $GLOBALS['captured_reply_command'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Reply deve responder HTTP 201.');
            assertSame('t-http-1', $command->ticketId, 'Command de reply deve mapear ticketId.');
            assertSame(99, $command->authorId, 'Command de reply deve mapear author_id.');
            assertSame('Aplicada correção.', $response['data']['message'], 'Response deve serializar mensagem.');
            assertSame('c-http-1', $response['data']['id'], 'Response deve serializar id do comentário.');
        },
        'ticket_controller_comments_returns_200_and_list_contract' => static function (): void {
            resetHttpContext();
            $_GET = ['page' => '2', 'per_page' => '1'];

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                new class implements ListTicketCommentsQueryHandler {
                    public function execute(ListTicketCommentsQuery $query): array
                    {
                        $GLOBALS['captured_comments_query'] = $query;

                        return [
                            TicketComment::create('c-http-list-1', $query->ticketId, 99, 'Primeira mensagem'),
                        ];
                    }
                },
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->comments(new ListTicketCommentsRequest(), 't-http-comments-1');
            $query = $GLOBALS['captured_comments_query'];

            assertSame('t-http-comments-1', $query->ticketId, 'Comments deve mapear ticketId para Query.');
            assertSame(2, $query->page, 'Comments deve mapear page para Query.');
            assertSame(1, $query->perPage, 'Comments deve mapear per_page para Query.');
            assertSame('c-http-list-1', $response['data'][0]['id'], 'Comments deve serializar id do comentário.');
            assertSame('Primeira mensagem', $response['data'][0]['message'], 'Comments deve serializar mensagem.');
        },
        'ticket_controller_comments_maps_not_found_to_404' => static function (): void {
            resetHttpContext();

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                new class implements ListTicketCommentsQueryHandler {
                    public function execute(ListTicketCommentsQuery $query): array
                    {
                        throw new TicketNotFoundException('Ticket não encontrado.');
                    }
                },
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->comments(new ListTicketCommentsRequest(), 't-http-comments-404');

            assertSame(404, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Comments deve mapear TicketNotFoundException para 404.');
            assertSame('TICKET_NOT_FOUND', $response['error']['code'], 'Comments deve retornar código TICKET_NOT_FOUND.');
        },
    ];

    function resetHttpContext(): void
    {
        $GLOBALS['ticketing_http_payload'] = '';
        $GLOBALS['ticketing_http_status_code'] = 200;
        unset($GLOBALS['ticketing_authenticated_user']);
        $_GET = [];
    }

    function noopCreateUseCase(): CreateTicketCommandHandler
    {
        return new class implements CreateTicketCommandHandler {
            public function handle(CreateTicketCommand $command): Ticket
            {
                return Ticket::open('noop-create', 1, 'noop', 'noop');
            }
        };
    }

    function noopListUseCase(): ListTicketsQueryHandler
    {
        return new class implements ListTicketsQueryHandler {
            public function execute(ListTicketsQuery $query): array
            {
                return [];
            }
        };
    }

    function noopAssignUseCase(): AssignTicketCommandHandler
    {
        return new class implements AssignTicketCommandHandler {
            public function handle(AssignTicketCommand $command): Ticket
            {
                return Ticket::open('noop-assign', 1, 'noop', 'noop');
            }
        };
    }

    function noopGetTicketDetailsUseCase(): GetTicketDetailsQueryHandler
    {
        return new class implements GetTicketDetailsQueryHandler {
            public function execute(GetTicketDetailsQuery $query): Ticket
            {
                return Ticket::open($query->ticketId, 1, 'noop', 'noop');
            }
        };
    }

    function noopListTicketCommentsUseCase(): ListTicketCommentsQueryHandler
    {
        return new class implements ListTicketCommentsQueryHandler {
            public function execute(ListTicketCommentsQuery $query): array
            {
                return [];
            }
        };
    }

    function noopCloseUseCase(): CloseTicketCommandHandler
    {
        return new class implements CloseTicketCommandHandler {
            public function handle(CloseTicketCommand $command): Ticket
            {
                return Ticket::open('noop-close', 1, 'noop', 'noop');
            }
        };
    }

    function noopReplyUseCase(): ReplyTicketCommandHandler
    {
        return new class implements ReplyTicketCommandHandler {
            public function handle(ReplyTicketCommand $command): TicketComment
            {
                return TicketComment::create('noop-reply', 'noop-ticket', 1, 'noop');
            }
        };
    }

    function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
            );
        }
    }

    function formatValue(mixed $value): string
    {
        if (is_object($value)) {
            if (method_exists($value, 'id')) {
                return sprintf('%s(%s)', $value::class, (string) $value->id());
            }

            return $value::class;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    $failures = [];

    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "PASS {$name}" . PHP_EOL;
        } catch (\Throwable $throwable) {
            $failures[] = sprintf('FAIL %s: %s', $name, $throwable->getMessage());
        }
    }

    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }

    exit(count($failures) === 0 ? 0 : 1);
}
