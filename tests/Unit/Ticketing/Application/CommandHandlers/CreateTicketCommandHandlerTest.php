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

use App\Modules\Ticketing\Application\CommandHandlers\CreateTicketCommandHandler;
use App\Modules\Ticketing\Application\Commands\CreateTicketCommand;
use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'create_ticket_command_handler_maps_command_to_input_dto' => static function (): void {
        $useCase = new FakeCreateTicketUseCase();
        $telemetry = new FakeQueryTelemetry();
        $handler = new CreateTicketCommandHandler($useCase, $telemetry);

        $handler->handle(new CreateTicketCommand(77, 'Falha login', 'Usuário não consegue autenticar.', 'trace-create-1'));
        $capturedInput = $useCase->capturedInput;

        assertSame(77, $capturedInput?->requesterId, 'Handler deve mapear requesterId.');
        assertSame('Falha login', $capturedInput?->title, 'Handler deve mapear title.');
        assertSame('Usuário não consegue autenticar.', $capturedInput?->description, 'Handler deve mapear description.');
        assertSame('create_ticket', $telemetry->lastQueryName, 'Handler deve registrar nome da operação.');
        assertSame('success', $telemetry->lastStatus, 'Handler deve registrar sucesso.');
        assertSame('trace-create-1', $telemetry->lastContext['trace_id'] ?? null, 'Handler deve propagar trace_id.');
    },
    'create_ticket_command_handler_logs_failure' => static function (): void {
        $telemetry = new FakeQueryTelemetry();
        $handler = new CreateTicketCommandHandler(new FailingCreateTicketUseCase(), $telemetry);

        try {
            $handler->handle(new CreateTicketCommand(7, 'Falha', 'Detalhe', 'trace-create-failure'));
            throw new RuntimeException('Era esperado lançar RuntimeException.');
        } catch (RuntimeException $exception) {
            assertSame('Falha ao criar ticket.', $exception->getMessage(), 'Handler deve propagar erro do caso de uso.');
        }

        assertSame('create_ticket', $telemetry->lastQueryName, 'Falha deve registrar nome da operação.');
        assertSame('failure', $telemetry->lastStatus, 'Falha deve registrar status de erro.');
        assertSame('trace-create-failure', $telemetry->lastContext['trace_id'] ?? null, 'Falha deve manter trace_id.');
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

final class FakeCreateTicketUseCase implements CreateTicketUseCase
{
    public ?CreateTicketInputDTO $capturedInput = null;

    public function execute(CreateTicketInputDTO $input): Ticket
    {
        $this->capturedInput = $input;

        return Ticket::open('t-create-handler-1', $input->requesterId, $input->title, $input->description);
    }
}

final class FailingCreateTicketUseCase implements CreateTicketUseCase
{
    public function execute(CreateTicketInputDTO $input): Ticket
    {
        throw new RuntimeException('Falha ao criar ticket.');
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
