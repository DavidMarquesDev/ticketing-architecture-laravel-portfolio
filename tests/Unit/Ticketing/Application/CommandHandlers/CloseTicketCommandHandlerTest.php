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

use App\Modules\Ticketing\Application\CommandHandlers\CloseTicketCommandHandler;
use App\Modules\Ticketing\Application\Commands\CloseTicketCommand;
use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'close_ticket_command_handler_maps_command_to_input_dto' => static function (): void {
        $useCase = new FakeCloseTicketUseCase();
        $handler = new CloseTicketCommandHandler($useCase);

        $handler->handle(new CloseTicketCommand('t-close-handler-1', 77));
        $capturedInput = $useCase->capturedInput;

        assertSame('t-close-handler-1', $capturedInput?->ticketId(), 'Handler deve mapear ticketId.');
        assertSame(77, $capturedInput?->actorUserId(), 'Handler deve mapear actorUserId.');
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

final class FakeCloseTicketUseCase implements CloseTicketUseCase
{
    public ?CloseTicketInputDTO $capturedInput = null;

    public function execute(CloseTicketInputDTO $input): Ticket
    {
        $this->capturedInput = $input;

        return Ticket::open($input->ticketId(), 1, 'noop', 'noop');
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
