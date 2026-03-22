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

use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Enums\TicketStatus;
use App\Modules\Ticketing\Domain\Exceptions\TicketStateException;

$tests = [
    'ticket_close_then_close_again_throws_conflict' => static function (): void {
        $ticket = Ticket::open('t-domain-1', 10, 'Falha', 'Erro 500');
        $ticket->close();

        assertSame(TicketStatus::CLOSED, $ticket->status(), 'Primeiro close deve fechar o ticket.');

        assertThrows(
            static fn (): null => $ticket->close(),
            TicketStateException::class,
            'Segundo close deve lançar conflito de estado.'
        );
    },
    'ticket_closed_cannot_be_assigned' => static function (): void {
        $ticket = Ticket::open('t-domain-2', 20, 'Falha', 'Erro 500');
        $ticket->close();

        assertThrows(
            static fn (): null => $ticket->assignTo(99),
            TicketStateException::class,
            'Ticket fechado não pode ser atribuído.'
        );
    },
    'ticket_closed_cannot_be_replied' => static function (): void {
        $ticket = Ticket::open('t-domain-3', 30, 'Falha', 'Erro 500');
        $ticket->close();

        assertThrows(
            static fn (): null => $ticket->reply(),
            TicketStateException::class,
            'Ticket fechado não pode receber resposta.'
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
