<?php

declare(strict_types=1);

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

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\ListTicketsService;
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'list_tickets_cache_hit' => static function (): void {
        $cachedTickets = [
            Ticket::open('t-cached-1', 10, 'Erro A', 'Falha A'),
            Ticket::open('t-cached-2', 11, 'Erro B', 'Falha B'),
        ];

        $ticketRepository = new FakeTicketRepository([]);
        $cache = new FakeTicketListCache($cachedTickets);
        $service = new ListTicketsService($ticketRepository, $cache);

        $result = $service->execute(new ListTicketsInputDTO(1, 10));

        assertSame($cachedTickets, $result, 'Resultado deve vir do cache.');
        assertSame(0, $ticketRepository->listCallCount, 'Repositório não deve ser consultado com cache hit.');
        assertTrue(!$cache->putCalled, 'Cache não deve receber put em cache hit.');
    },
    'list_tickets_cache_miss' => static function (): void {
        $repositoryTickets = [
            Ticket::open('t-repo-1', 20, 'Erro C', 'Falha C'),
            Ticket::open('t-repo-2', 21, 'Erro D', 'Falha D'),
        ];

        $ticketRepository = new FakeTicketRepository($repositoryTickets);
        $cache = new FakeTicketListCache(null);
        $service = new ListTicketsService($ticketRepository, $cache);

        $result = $service->execute(new ListTicketsInputDTO(1, 15));

        assertSame(2, count($result), 'Resultado em cache miss deve conter todos os tickets.');
        assertSame('t-repo-2', $result[0]->id(), 'Resultado em cache miss deve respeitar ordenação padrão.');
        assertSame('t-repo-1', $result[1]->id(), 'Resultado em cache miss deve respeitar ordenação padrão.');
        assertSame(1, $ticketRepository->listCallCount, 'Repositório deve ser consultado uma vez.');
        assertTrue($cache->putCalled, 'Cache deve receber put após cache miss.');
        assertSame(1, $cache->lastPage, 'Página usada no put deve ser preservada.');
        assertSame(15, $cache->lastPerPage, 'PerPage usado no put deve ser preservado.');
        assertSame(120, $cache->lastSeconds, 'TTL padrão do cache deve ser 120.');
    },
    'list_tickets_with_advanced_filters_bypasses_cache_and_applies_query' => static function (): void {
        $ticketOpen = Ticket::open('t-advanced-1', 10, 'Checkout lento', 'Erro intermitente');
        $ticketPending = Ticket::open('t-advanced-2', 10, 'Checkout pendente', 'Aguardando retorno');
        $ticketPending->reply();
        $ticketClosed = Ticket::open('t-advanced-3', 12, 'Outro assunto', 'Texto sem match');
        $ticketClosed->close();

        $ticketRepository = new FakeTicketRepository([$ticketOpen, $ticketPending, $ticketClosed]);
        $cache = new FakeTicketListCache(null);
        $service = new ListTicketsService($ticketRepository, $cache);

        $result = $service->execute(
            new ListTicketsInputDTO(
                page: 1,
                perPage: 10,
                status: 'pending',
                requesterId: 10,
                assigneeId: null,
                search: 'checkout',
                sortBy: 'title',
                sortDir: 'asc'
            )
        );

        assertSame(1, count($result), 'Consulta avançada deve aplicar filtros combinados.');
        assertSame('t-advanced-2', $result[0]->id(), 'Consulta avançada deve retornar ticket esperado.');
        assertSame(1, $ticketRepository->listCallCount, 'Consulta avançada deve consultar repositório uma vez.');
        assertSame(1, $ticketRepository->lastPage, 'Consulta avançada deve preservar a página solicitada.');
        assertSame(10, $ticketRepository->lastPerPage, 'Consulta avançada deve preservar perPage solicitado.');
        assertSame('pending', $ticketRepository->lastStatus, 'Consulta avançada deve encaminhar filtro status.');
        assertSame(10, $ticketRepository->lastRequesterId, 'Consulta avançada deve encaminhar filtro requester.');
        assertSame('checkout', $ticketRepository->lastSearch, 'Consulta avançada deve encaminhar busca textual.');
        assertSame('title', $ticketRepository->lastSortBy, 'Consulta avançada deve encaminhar sort_by.');
        assertSame('asc', $ticketRepository->lastSortDir, 'Consulta avançada deve encaminhar sort_dir.');
        assertTrue($cache->putCalled, 'Consulta avançada deve gravar cache com chave composta.');
    },
];

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
        );
    }
}

function formatValue(mixed $value): string
{
    if (is_object($value)) {
        return $value::class;
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return 'null';
    }

    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    return (string) $value;
}

final class FakeTicketRepository implements TicketRepositoryPort
{
    public int $listCallCount = 0;
    public int $lastPage = 0;
    public int $lastPerPage = 0;
    public ?string $lastStatus = null;
    public ?int $lastRequesterId = null;
    public ?int $lastAssigneeId = null;
    public ?string $lastSearch = null;
    public string $lastSortBy = 'id';
    public string $lastSortDir = 'desc';

    public function __construct(
        public array $tickets
    ) {
    }

    public function save(Ticket $ticket): Ticket
    {
        $this->tickets[$ticket->id()] = $ticket;

        return $ticket;
    }

    public function list(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): array
    {
        $this->listCallCount++;
        $this->lastPage = $page;
        $this->lastPerPage = $perPage;
        $this->lastStatus = $status;
        $this->lastRequesterId = $requesterId;
        $this->lastAssigneeId = $assigneeId;
        $this->lastSearch = $search;
        $this->lastSortBy = $sortBy;
        $this->lastSortDir = $sortDir;

        $filtered = array_values(
            array_filter(
                $this->tickets,
                static function (Ticket $ticket) use ($status, $requesterId, $assigneeId, $search): bool {
                    if ($status !== null && $ticket->status()->value !== $status) {
                        return false;
                    }

                    if ($requesterId !== null && $ticket->requesterId() !== $requesterId) {
                        return false;
                    }

                    if ($assigneeId !== null && $ticket->assigneeId() !== $assigneeId) {
                        return false;
                    }

                    if ($search !== null) {
                        $needle = strtolower(trim($search));
                        $title = strtolower($ticket->title());
                        $description = strtolower($ticket->description());

                        return str_contains($title, $needle) || str_contains($description, $needle);
                    }

                    return true;
                }
            )
        );
        usort(
            $filtered,
            static function (Ticket $left, Ticket $right) use ($sortBy, $sortDir): int {
                $comparison = match ($sortBy) {
                    'status' => strcmp($left->status()->value, $right->status()->value),
                    'title' => strcmp($left->title(), $right->title()),
                    'requester_id' => $left->requesterId() <=> $right->requesterId(),
                    'assignee_id' => ($left->assigneeId() ?? 0) <=> ($right->assigneeId() ?? 0),
                    default => strcmp($left->id(), $right->id()),
                };

                return $sortDir === 'asc' ? $comparison : $comparison * -1;
            }
        );
        $offset = max(0, ($page - 1) * $perPage);

        return array_values(array_slice($filtered, $offset, $perPage));
    }

    public function findById(string $ticketId): ?Ticket
    {
        $ticket = $this->tickets[$ticketId] ?? null;

        return $ticket instanceof Ticket ? $ticket : null;
    }
}

final class FakeTicketListCache implements TicketListCachePort
{
    public bool $putCalled = false;
    public int $lastPage = 0;
    public int $lastPerPage = 0;
    public int $lastSeconds = 0;
    public array $lastTickets = [];
    public ?string $lastStatus = null;
    public ?int $lastRequesterId = null;
    public ?int $lastAssigneeId = null;
    public ?string $lastSearch = null;
    public string $lastSortBy = 'id';
    public string $lastSortDir = 'desc';

    public function __construct(
        private readonly ?array $cachedTickets
    ) {
    }

    public function get(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): ?array
    {
        return $this->cachedTickets;
    }

    public function put(
        int $page,
        int $perPage,
        array $tickets,
        int $seconds,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): void
    {
        $this->putCalled = true;
        $this->lastPage = $page;
        $this->lastPerPage = $perPage;
        $this->lastTickets = $tickets;
        $this->lastSeconds = $seconds;
        $this->lastStatus = $status;
        $this->lastRequesterId = $requesterId;
        $this->lastAssigneeId = $assigneeId;
        $this->lastSearch = $search;
        $this->lastSortBy = $sortBy;
        $this->lastSortDir = $sortDir;
    }

    public function forgetAll(): void
    {
    }
}

$failures = [];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}" . PHP_EOL;
    } catch (Throwable $throwable) {
        $failures[] = sprintf('FAIL %s: %s', $name, $throwable->getMessage());
    }
}

foreach ($failures as $failure) {
    echo $failure . PHP_EOL;
}

exit(count($failures) === 0 ? 0 : 1);
