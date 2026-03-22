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

use App\Modules\Ticketing\Application\CommandHandlers\AssignTicketCommandHandler;
use App\Modules\Ticketing\Application\Commands\AssignTicketCommand;
use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'assign_ticket_command_handler_maps_command_to_input_dto' => static function (): void {
        $useCase = new FakeAssignTicketUseCase();
        $telemetry = new FakeQueryTelemetry();
        $handler = new AssignTicketCommandHandler($useCase, $telemetry);

        $handler->handle(new AssignTicketCommand('t-handler-1', 99, 77, 'trace-assign-1'));
        $capturedInput = $useCase->capturedInput;

        assertSame('t-handler-1', $capturedInput?->ticketId, 'Handler deve mapear ticketId.');
        assertSame(99, $capturedInput?->assigneeId, 'Handler deve mapear assigneeId.');
        assertSame(77, $capturedInput?->actorUserId, 'Handler deve mapear actorUserId.');
        assertSame('assign_ticket', $telemetry->lastQueryName, 'Handler deve registrar nome da operação.');
        assertSame('success', $telemetry->lastStatus, 'Handler deve registrar sucesso.');
        assertSame('trace-assign-1', $telemetry->lastContext['trace_id'] ?? null, 'Handler deve propagar trace_id.');
    },
    'assign_ticket_command_handler_logs_failure' => static function (): void {
        $telemetry = new FakeQueryTelemetry();
        $handler = new AssignTicketCommandHandler(new FailingAssignTicketUseCase(), $telemetry);

        try {
            $handler->handle(new AssignTicketCommand('t-handler-fail', 22, 11, 'trace-assign-failure'));
            throw new RuntimeException('Era esperado lançar RuntimeException.');
        } catch (RuntimeException $exception) {
            assertSame('Falha na atribuição.', $exception->getMessage(), 'Handler deve propagar erro do caso de uso.');
        }

        assertSame('assign_ticket', $telemetry->lastQueryName, 'Falha deve registrar nome da operação.');
        assertSame('failure', $telemetry->lastStatus, 'Falha deve registrar status de erro.');
        assertSame('trace-assign-failure', $telemetry->lastContext['trace_id'] ?? null, 'Falha deve manter trace_id.');
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

final class FakeAssignTicketUseCase implements AssignTicketUseCase
{
    public ?AssignTicketInputDTO $capturedInput = null;

    public function execute(AssignTicketInputDTO $input): Ticket
    {
        $this->capturedInput = $input;

        return Ticket::open($input->ticketId, 1, 'noop', 'noop');
    }
}

final class FailingAssignTicketUseCase implements AssignTicketUseCase
{
    public function execute(AssignTicketInputDTO $input): Ticket
    {
        throw new RuntimeException('Falha na atribuição.');
    }
}

final class FakeQueryTelemetry implements QueryTelemetryPort
{
    public ?string $lastQueryName = null;

    public ?string $lastStatus = null;

    /**
     * @var array<string, int|string|null>
     */
    public array $lastContext = [];

    public function record(string $queryName, string $status, float $durationMs, array $context = []): void
    {
        $this->lastQueryName = $queryName;
        $this->lastStatus = $status;
        $this->lastContext = $context;
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
