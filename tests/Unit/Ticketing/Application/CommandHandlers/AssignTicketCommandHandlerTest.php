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
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'assign_ticket_command_handler_maps_command_to_input_dto' => static function (): void {
        $useCase = new FakeAssignTicketUseCase();
        $handler = new AssignTicketCommandHandler($useCase);

        $handler->handle(new AssignTicketCommand('t-handler-1', 99, 77));
        $capturedInput = $useCase->capturedInput;

        assertSame('t-handler-1', $capturedInput?->ticketId, 'Handler deve mapear ticketId.');
        assertSame(99, $capturedInput?->assigneeId, 'Handler deve mapear assigneeId.');
        assertSame(77, $capturedInput?->actorUserId, 'Handler deve mapear actorUserId.');
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
