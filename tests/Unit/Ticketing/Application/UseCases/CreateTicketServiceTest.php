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

use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\CreateTicketService;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Enums\TicketStatus;

$tests = [
    'create_ticket_success' => static function (): void {
        $ticketRepository = new FakeTicketRepository([]);
        $dispatcher = new FakeEventDispatcher();
        $service = new CreateTicketService($ticketRepository, $dispatcher);

        $ticket = $service->execute(new CreateTicketInputDTO(10, 'Erro checkout', 'Falha 500'));

        assertSame(10, $ticket->requesterId(), 'Requester deve ser mantido.');
        assertSame('Erro checkout', $ticket->title(), 'Título deve ser mantido.');
        assertSame('Falha 500', $ticket->description(), 'Descrição deve ser mantida.');
        assertSame(TicketStatus::OPEN, $ticket->status(), 'Ticket novo deve iniciar OPEN.');
        assertTrue((bool) preg_match('/^[a-f0-9]{32}$/', $ticket->id()), 'ID deve ser hash hexadecimal de 32 chars.');
        assertTrue(isset($ticketRepository->tickets[$ticket->id()]), 'Ticket deve ser persistido.');
        assertTrue(isset($dispatcher->events[0]) && $dispatcher->events[0] instanceof TicketCreated, 'Evento TicketCreated deve ser disparado.');
        assertSame($ticket->id(), $dispatcher->events[0]->ticketId, 'Evento deve conter id do ticket salvo.');
        assertSame(10, $dispatcher->events[0]->requesterId, 'Evento deve conter requester correto.');
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

final class FakeEventDispatcher implements EventDispatcherPort
{
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
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
