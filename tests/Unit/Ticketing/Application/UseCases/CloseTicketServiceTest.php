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

use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\UserReadRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\CloseTicketService;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketClosed;
use App\Modules\Ticketing\Domain\Enums\TicketStatus;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use App\Modules\Ticketing\Domain\Exceptions\TicketStateException;

$tests = [
    'close_ticket_success' => static function (): void {
        $ticket = Ticket::open('t-1', 10, 'Erro checkout', 'Falha 500');
        $ticketRepository = new FakeTicketRepository([$ticket->id() => $ticket]);
        $cache = new CloseFakeTicketListCache();
        $lock = new FakeLock();
        $dispatcher = new FakeEventDispatcher();
        $userReadRepository = new FakeUserReadRepository(true);

        $service = new CloseTicketService($ticketRepository, $cache, $lock, $dispatcher, $userReadRepository);
        $closedTicket = $service->execute(new CloseTicketInputDTO('t-1', 10));

        assertSame(TicketStatus::CLOSED, $closedTicket->status(), 'Status deve ser CLOSED.');
        assertTrue($cache->forgetCalled, 'Cache deve ser invalidado no fechamento.');
        assertSame('ticket:close:t-1', $lock->lastKey, 'Lock deve usar chave esperada.');
        assertSame(5, $lock->lastSeconds, 'Lock deve usar TTL esperado.');
        assertSame(10, $userReadRepository->lastUserId, 'Autorização deve usar usuário autenticado.');
        assertSame('admin,agent', implode(',', $userReadRepository->lastRoles), 'Autorização deve validar roles permitidas.');
        assertTrue(isset($dispatcher->events[0]) && $dispatcher->events[0] instanceof TicketClosed, 'Evento TicketClosed deve ser disparado.');
    },
    'close_ticket_not_found' => static function (): void {
        $ticketRepository = new FakeTicketRepository([]);
        $cache = new CloseFakeTicketListCache();
        $lock = new FakeLock();
        $dispatcher = new FakeEventDispatcher();
        $userReadRepository = new FakeUserReadRepository(true);
        $service = new CloseTicketService($ticketRepository, $cache, $lock, $dispatcher, $userReadRepository);

        expectException(
            static fn (): mixed => $service->execute(new CloseTicketInputDTO('inexistente', 10)),
            TicketNotFoundException::class
        );
    },
    'close_ticket_state_conflict' => static function (): void {
        $ticket = Ticket::open('t-closed', 10, 'Erro checkout', 'Falha 500');
        $ticket->close();

        $ticketRepository = new FakeTicketRepository([$ticket->id() => $ticket]);
        $cache = new CloseFakeTicketListCache();
        $lock = new FakeLock();
        $dispatcher = new FakeEventDispatcher();
        $userReadRepository = new FakeUserReadRepository(true);
        $service = new CloseTicketService($ticketRepository, $cache, $lock, $dispatcher, $userReadRepository);

        expectException(
            static fn (): mixed => $service->execute(new CloseTicketInputDTO('t-closed', 10)),
            TicketStateException::class
        );
    },
    'close_ticket_forbidden_without_allowed_role' => static function (): void {
        $ticket = Ticket::open('t-forbidden', 10, 'Erro checkout', 'Falha 500');
        $ticketRepository = new FakeTicketRepository([$ticket->id() => $ticket]);
        $cache = new CloseFakeTicketListCache();
        $lock = new FakeLock();
        $dispatcher = new FakeEventDispatcher();
        $userReadRepository = new FakeUserReadRepository(false);
        $service = new CloseTicketService($ticketRepository, $cache, $lock, $dispatcher, $userReadRepository);

        expectException(
            static fn (): mixed => $service->execute(new CloseTicketInputDTO('t-forbidden', 33)),
            \DomainException::class
        );
        assertSame(false, $cache->forgetCalled, 'Cache não deve ser invalidado quando autorização falha.');
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

function expectException(callable $callback, string $expectedException): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $expectedException) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Exceção diferente da esperada. Esperada: %s. Recebida: %s.',
                $expectedException,
                $throwable::class
            )
        );
    }

    throw new RuntimeException(sprintf('Exceção esperada não foi lançada: %s.', $expectedException));
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
        return array_values($this->tickets);
    }

    public function findById(string $ticketId): ?Ticket
    {
        $ticket = $this->tickets[$ticketId] ?? null;

        return $ticket instanceof Ticket ? $ticket : null;
    }
}

final class CloseFakeTicketListCache implements TicketListCachePort
{
    public bool $forgetCalled = false;

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
        return null;
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
    }

    public function forgetAll(): void
    {
        $this->forgetCalled = true;
    }
}

final class FakeLock implements DistributedLockPort
{
    public string $lastKey = '';

    public int $lastSeconds = 0;

    public function execute(string $key, int $seconds, callable $callback): mixed
    {
        $this->lastKey = $key;
        $this->lastSeconds = $seconds;

        return $callback();
    }
}

final class FakeEventDispatcher implements EventDispatcherPort
{
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
    }
}

final class FakeUserReadRepository implements UserReadRepositoryPort
{
    public int $lastUserId = 0;

    public array $lastRoles = [];

    public function __construct(
        private readonly bool $hasRole
    ) {
    }

    public function existsById(int $userId): bool
    {
        return true;
    }

    public function hasAnyRole(int $userId, array $roles): bool
    {
        $this->lastUserId = $userId;
        $this->lastRoles = $roles;

        return $this->hasRole;
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
