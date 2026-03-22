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

use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
use App\Modules\Ticketing\Application\QueryHandlers\GetTicketDetailsQueryHandler;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

$tests = [
    'get_ticket_details_returns_ticket_when_found' => static function (): void {
        $ticket = Ticket::open('t-details-1', 10, 'Erro checkout', 'Falha 500');
        $repository = new FakeTicketRepository([
            $ticket->id() => $ticket,
        ]);
        $handler = new GetTicketDetailsQueryHandler($repository);

        $result = $handler->execute(new GetTicketDetailsQuery('t-details-1'));

        assertSame('t-details-1', $result->id(), 'Handler deve retornar ticket encontrado.');
    },
    'get_ticket_details_throws_not_found_when_ticket_does_not_exist' => static function (): void {
        $repository = new FakeTicketRepository([]);
        $handler = new GetTicketDetailsQueryHandler($repository);

        assertThrows(
            static fn (): Ticket => $handler->execute(new GetTicketDetailsQuery('t-missing')),
            TicketNotFoundException::class,
            'Handler deve lançar TicketNotFoundException quando ticket não existir.'
        );
    },
];

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
        );
    }
}

function assertThrows(callable $callback, string $expectedException, string $message): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $expectedException) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                '%s Exceção esperada: %s. Exceção atual: %s.',
                $message,
                $expectedException,
                $throwable::class
            )
        );
    }

    throw new RuntimeException(
        sprintf('%s Exceção esperada: %s. Nenhuma exceção foi lançada.', $message, $expectedException)
    );
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
