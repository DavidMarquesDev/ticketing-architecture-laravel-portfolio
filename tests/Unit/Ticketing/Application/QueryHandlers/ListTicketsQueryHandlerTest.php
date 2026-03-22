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
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Queries\ListTicketsQuery;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketsQueryHandler;
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'list_tickets_query_handler_maps_query_to_input_dto' => static function (): void {
        $useCase = new FakeListTicketsUseCase();
        $telemetry = new FakeQueryTelemetry();
        $handler = new ListTicketsQueryHandler($useCase, $telemetry);

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
        assertSame('list_tickets', $telemetry->lastQueryName, 'Handler deve registrar nome da query.');
        assertSame('success', $telemetry->lastStatus, 'Handler deve registrar status de sucesso.');
        assertSame(1, $telemetry->recordsCount, 'Handler deve registrar uma métrica por execução.');
    },
    'list_tickets_query_handler_logs_failure_when_use_case_throws' => static function (): void {
        $telemetry = new FakeQueryTelemetry();
        $handler = new ListTicketsQueryHandler(new FailingListTicketsUseCase(), $telemetry);

        assertThrows(
            static fn (): array => $handler->execute(new ListTicketsQuery(1, 10)),
            RuntimeException::class,
            'Handler deve propagar exceção do caso de uso.'
        );

        assertSame('list_tickets', $telemetry->lastQueryName, 'Handler deve registrar nome da query na falha.');
        assertSame('failure', $telemetry->lastStatus, 'Handler deve registrar status de falha.');
        assertSame(1, $telemetry->recordsCount, 'Handler deve registrar uma métrica na falha.');
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

final class FailingListTicketsUseCase implements ListTicketsUseCase
{
    public function execute(ListTicketsInputDTO $input): array
    {
        throw new RuntimeException('Falha esperada.');
    }
}

final class FakeQueryTelemetry implements QueryTelemetryPort
{
    public string $lastQueryName = '';

    public string $lastStatus = '';

    public float $lastDurationMs = 0.0;

    public int $recordsCount = 0;

    public function record(string $queryName, string $status, float $durationMs, array $context = []): void
    {
        $this->lastQueryName = $queryName;
        $this->lastStatus = $status;
        $this->lastDurationMs = $durationMs;
        $this->recordsCount++;
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
