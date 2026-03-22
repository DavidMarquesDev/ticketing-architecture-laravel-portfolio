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
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Queries\ListTicketsQuery;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketsQueryHandler;
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'list_tickets_query_handler_maps_query_to_input_dto' => static function (): void {
        $useCase = new FakeListTicketsUseCase();
        $handler = new ListTicketsQueryHandler($useCase);

        $handler->execute(
            new ListTicketsQuery(
                page: 2,
                perPage: 20,
                status: 'pending',
                requesterId: 10,
                assigneeId: 88,
                search: 'checkout',
                sortBy: 'title',
                sortDir: 'asc'
            )
        );

        $capturedInput = $useCase->capturedInput;

        assertSame(2, $capturedInput?->page, 'Handler deve mapear page.');
        assertSame(20, $capturedInput?->perPage, 'Handler deve mapear perPage.');
        assertSame('pending', $capturedInput?->status, 'Handler deve mapear status.');
        assertSame(10, $capturedInput?->requesterId, 'Handler deve mapear requesterId.');
        assertSame(88, $capturedInput?->assigneeId, 'Handler deve mapear assigneeId.');
        assertSame('checkout', $capturedInput?->search, 'Handler deve mapear search.');
        assertSame('title', $capturedInput?->sortBy, 'Handler deve mapear sortBy.');
        assertSame('asc', $capturedInput?->sortDir, 'Handler deve mapear sortDir.');
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

final class FakeListTicketsUseCase implements ListTicketsUseCase
{
    public ?ListTicketsInputDTO $capturedInput = null;

    /**
     * @return array<int, Ticket>
     */
    public function execute(ListTicketsInputDTO $input): array
    {
        $this->capturedInput = $input;

        return [Ticket::open('t-query-handler', 1, 'noop', 'noop')];
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
