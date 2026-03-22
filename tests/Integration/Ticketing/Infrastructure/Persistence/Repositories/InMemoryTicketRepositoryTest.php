<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__, 6) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\InMemoryTicketRepository;

$tests = [
    'inmemory_ticket_repository_save_and_find' => static function (): void {
        resetRepositoryState();
        $repository = new InMemoryTicketRepository();
        $ticket = Ticket::open('t-1', 10, 'Erro checkout', 'Falha 500');

        $saved = $repository->save($ticket);
        $found = $repository->findById('t-1');

        assertSame($ticket, $saved, 'Save deve retornar a instância persistida.');
        assertSame($ticket, $found, 'findById deve retornar o ticket salvo.');
    },
    'inmemory_ticket_repository_list_with_pagination' => static function (): void {
        resetRepositoryState();
        $repository = new InMemoryTicketRepository();
        $repository->save(Ticket::open('t-1', 10, 'Erro 1', 'Falha 1'));
        $repository->save(Ticket::open('t-2', 11, 'Erro 2', 'Falha 2'));
        $repository->save(Ticket::open('t-3', 12, 'Erro 3', 'Falha 3'));

        $pageOne = $repository->list(1, 2);
        $pageTwo = $repository->list(2, 2);

        assertSame(2, count($pageOne), 'Página 1 deve conter dois itens.');
        assertSame(1, count($pageTwo), 'Página 2 deve conter um item.');
        assertSame('t-1', $pageOne[0]->id(), 'Primeiro item da página 1 deve ser t-1.');
        assertSame('t-2', $pageOne[1]->id(), 'Segundo item da página 1 deve ser t-2.');
        assertSame('t-3', $pageTwo[0]->id(), 'Primeiro item da página 2 deve ser t-3.');
    },
    'inmemory_ticket_repository_find_nonexistent_returns_null' => static function (): void {
        resetRepositoryState();
        $repository = new InMemoryTicketRepository();

        $found = $repository->findById('ticket-inexistente');

        assertSame(null, $found, 'findById deve retornar null para id inexistente.');
    },
];

function resetRepositoryState(): void
{
    $reflection = new ReflectionClass(InMemoryTicketRepository::class);
    $property = $reflection->getProperty('tickets');
    $property->setValue(null, []);
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

    return (string) $value;
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
