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

        $result = $service->execute(new ListTicketsInputDTO(2, 15));

        assertSame($repositoryTickets, $result, 'Resultado deve vir do repositório em cache miss.');
        assertSame(1, $ticketRepository->listCallCount, 'Repositório deve ser consultado uma vez.');
        assertTrue($cache->putCalled, 'Cache deve receber put após cache miss.');
        assertSame(2, $cache->lastPage, 'Página usada no put deve ser preservada.');
        assertSame(15, $cache->lastPerPage, 'PerPage usado no put deve ser preservado.');
        assertSame(120, $cache->lastSeconds, 'TTL padrão do cache deve ser 120.');
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

    return (string) $value;
}

final class FakeTicketRepository implements TicketRepositoryPort
{
    public int $listCallCount = 0;

    public function __construct(
        public array $tickets
    ) {
    }

    public function save(Ticket $ticket): Ticket
    {
        $this->tickets[$ticket->id()] = $ticket;

        return $ticket;
    }

    public function list(int $page, int $perPage): array
    {
        $this->listCallCount++;

        return $this->tickets;
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

    public function __construct(
        private readonly ?array $cachedTickets
    ) {
    }

    public function get(int $page, int $perPage): ?array
    {
        return $this->cachedTickets;
    }

    public function put(int $page, int $perPage, array $tickets, int $seconds): void
    {
        $this->putCalled = true;
        $this->lastPage = $page;
        $this->lastPerPage = $perPage;
        $this->lastTickets = $tickets;
        $this->lastSeconds = $seconds;
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
