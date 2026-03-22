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

    use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
    use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
    use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
    use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
    use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
    use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
    use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
    use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
    use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsUseCase;
    use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
    use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
    use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
    use App\Modules\Ticketing\Domain\Entities\Ticket;
    use App\Modules\Ticketing\Domain\Entities\TicketComment;
    use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
    use App\Modules\Ticketing\Interface\Http\Controllers\TicketController;
    use App\Modules\Ticketing\Interface\Http\Requests\AssignTicketRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\CloseTicketRequest;
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
                new class implements CreateTicketUseCase {
                    public function execute(CreateTicketInputDTO $input): Ticket
                    {
                        $GLOBALS['captured_create_input'] = $input;

                        return Ticket::open('t-http-1', $input->requesterId, $input->title, $input->description);
                    }
                },
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->store(new StoreTicketRequest());
            $input = $GLOBALS['captured_create_input'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Store deve responder HTTP 201.');
            assertSame(10, $input->requesterId, 'DTO de criação deve mapear requester_id.');
            assertSame('Falha no checkout', $input->title, 'DTO de criação deve mapear title.');
            assertSame('Erro 500 ao finalizar pagamento', $input->description, 'DTO de criação deve mapear description.');
            assertSame('t-http-1', $response['data']['id'], 'Response deve retornar id do ticket.');
            assertSame('open', $response['data']['status'], 'Response deve retornar status do ticket.');
        },
        'ticket_controller_store_maps_authenticated_user_to_requester_id' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 55, 'roles' => ['customer']];

            $controller = new TicketController(
                new class implements CreateTicketUseCase {
                    public function execute(CreateTicketInputDTO $input): Ticket
                    {
                        $GLOBALS['captured_create_input'] = $input;

                        return Ticket::open('t-http-auth-1', $input->requesterId, $input->title, $input->description);
                    }
                },
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
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

            $input = $GLOBALS['captured_create_input'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Store deve responder HTTP 201.');
            assertSame(55, $input->requesterId, 'Store deve usar o usuário autenticado como requester.');
            assertSame('t-http-auth-1', $response['data']['id'], 'Response deve retornar id serializado.');
        },
        'ticket_controller_store_returns_401_when_user_is_not_authenticated' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = null;
            $GLOBALS['create_use_case_called'] = false;

            $controller = new TicketController(
                new class implements CreateTicketUseCase {
                    public function execute(CreateTicketInputDTO $input): Ticket
                    {
                        $GLOBALS['create_use_case_called'] = true;

                        return Ticket::open('t-http-unauth', $input->requesterId, $input->title, $input->description);
                    }
                },
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
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
            assertSame(false, $GLOBALS['create_use_case_called'], 'Store não deve executar caso de uso sem autenticação.');
        },
        'ticket_controller_index_reads_query_and_returns_list_contract' => static function (): void {
            resetHttpContext();
            $_GET = ['page' => '2', 'per_page' => '1'];

            $controller = new TicketController(
                noopCreateUseCase(),
                new class implements ListTicketsUseCase {
                    public function execute(ListTicketsInputDTO $input): array
                    {
                        $GLOBALS['captured_list_input'] = $input;

                        return [Ticket::open('t-http-2', 20, 'Título 2', 'Descrição 2')];
                    }
                },
                noopGetTicketDetailsUseCase(),
                noopAssignUseCase(),
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->index(new ListTicketsRequest());
            $input = $GLOBALS['captured_list_input'];

            assertSame(2, $input->page, 'Index deve mapear page para DTO.');
            assertSame(1, $input->perPage, 'Index deve mapear per_page para DTO.');
            assertSame('t-http-2', $response['data'][0]['id'], 'Index deve serializar id.');
            assertSame('Título 2', $response['data'][0]['title'], 'Index deve serializar título.');
        },
        'ticket_controller_show_returns_200_and_data_contract' => static function (): void {
            resetHttpContext();

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                new class implements GetTicketDetailsUseCase {
                    public function execute(GetTicketDetailsQuery $query): Ticket
                    {
                        $GLOBALS['captured_show_query'] = $query;

                        return Ticket::open($query->ticketId, 20, 'Título detalhado', 'Descrição detalhada');
                    }
                },
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
                new class implements GetTicketDetailsUseCase {
                    public function execute(GetTicketDetailsQuery $query): Ticket
                    {
                        throw new TicketNotFoundException('Ticket não encontrado.');
                    }
                },
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
            $GLOBALS['ticketing_http_payload'] = json_encode(['assignee_id' => 77], JSON_THROW_ON_ERROR);

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                new class implements AssignTicketUseCase {
                    public function execute(AssignTicketInputDTO $input): Ticket
                    {
                        throw new TicketNotFoundException('Ticket não encontrado.');
                    }
                },
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $response = $controller->assign(new AssignTicketRequest(), 'ticket-inexistente');

            assertSame(404, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Assign deve mapear TicketNotFoundException para 404.');
            assertSame('TICKET_NOT_FOUND', $response['error']['code'], 'Payload de erro deve usar código padronizado.');
        },
        'ticket_controller_assign_returns_403_when_user_has_no_permission' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_authenticated_user'] = ['id' => 33, 'roles' => ['customer']];
            $GLOBALS['assign_use_case_called'] = false;

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                new class implements AssignTicketUseCase {
                    public function execute(AssignTicketInputDTO $input): Ticket
                    {
                        $GLOBALS['assign_use_case_called'] = true;

                        return Ticket::open('t-http-forbidden', 1, 'noop', 'noop');
                    }
                },
                noopCloseUseCase(),
                noopReplyUseCase()
            );

            $GLOBALS['ticketing_http_payload'] = json_encode(['assignee_id' => 77], JSON_THROW_ON_ERROR);

            $response = $controller->assign(new AssignTicketRequest(), 'ticket-forbidden');

            assertSame(403, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Assign deve responder 403 sem permissão.');
            assertSame('FORBIDDEN', $response['error']['code'], 'Assign deve retornar código FORBIDDEN.');
            assertSame(false, $GLOBALS['assign_use_case_called'], 'Assign não deve executar caso de uso sem permissão.');
        },
        'ticket_controller_close_maps_conflict_to_409' => static function (): void {
            resetHttpContext();

            $controller = new TicketController(
                noopCreateUseCase(),
                noopListUseCase(),
                noopGetTicketDetailsUseCase(),
                noopAssignUseCase(),
                new class implements CloseTicketUseCase {
                    public function execute(CloseTicketInputDTO $input): Ticket
                    {
                        throw new \RuntimeException('Ticket já está fechado.');
                    }
                },
                noopReplyUseCase()
            );

            $response = $controller->close(new CloseTicketRequest(), 't-closed');

            assertSame(409, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Close deve mapear conflito para 409.');
            assertSame('TICKET_CONFLICT', $response['error']['code'], 'Payload de erro deve usar TICKET_CONFLICT.');
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
                noopAssignUseCase(),
                noopCloseUseCase(),
                new class implements ReplyTicketUseCase {
                    public function execute(ReplyTicketInputDTO $input): TicketComment
                    {
                        $GLOBALS['captured_reply_input'] = $input;

                        return TicketComment::create('c-http-1', $input->ticketId, $input->authorId, $input->message);
                    }
                }
            );

            $response = $controller->reply(new ReplyTicketRequest(), 't-http-1');
            $input = $GLOBALS['captured_reply_input'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Reply deve responder HTTP 201.');
            assertSame('t-http-1', $input->ticketId, 'DTO de reply deve mapear ticketId.');
            assertSame(99, $input->authorId, 'DTO de reply deve mapear author_id.');
            assertSame('Aplicada correção.', $response['data']['message'], 'Response deve serializar mensagem.');
            assertSame('c-http-1', $response['data']['id'], 'Response deve serializar id do comentário.');
        },
    ];

    function resetHttpContext(): void
    {
        $GLOBALS['ticketing_http_payload'] = '';
        $GLOBALS['ticketing_http_status_code'] = 200;
        unset($GLOBALS['ticketing_authenticated_user']);
        $_GET = [];
    }

    function noopCreateUseCase(): CreateTicketUseCase
    {
        return new class implements CreateTicketUseCase {
            public function execute(CreateTicketInputDTO $input): Ticket
            {
                return Ticket::open('noop-create', 1, 'noop', 'noop');
            }
        };
    }

    function noopListUseCase(): ListTicketsUseCase
    {
        return new class implements ListTicketsUseCase {
            public function execute(ListTicketsInputDTO $input): array
            {
                return [];
            }
        };
    }

    function noopAssignUseCase(): AssignTicketUseCase
    {
        return new class implements AssignTicketUseCase {
            public function execute(AssignTicketInputDTO $input): Ticket
            {
                return Ticket::open('noop-assign', 1, 'noop', 'noop');
            }
        };
    }

    function noopGetTicketDetailsUseCase(): GetTicketDetailsUseCase
    {
        return new class implements GetTicketDetailsUseCase {
            public function execute(GetTicketDetailsQuery $query): Ticket
            {
                return Ticket::open($query->ticketId, 1, 'noop', 'noop');
            }
        };
    }

    function noopCloseUseCase(): CloseTicketUseCase
    {
        return new class implements CloseTicketUseCase {
            public function execute(CloseTicketInputDTO $input): Ticket
            {
                return Ticket::open('noop-close', 1, 'noop', 'noop');
            }
        };
    }

    function noopReplyUseCase(): ReplyTicketUseCase
    {
        return new class implements ReplyTicketUseCase {
            public function execute(ReplyTicketInputDTO $input): TicketComment
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
