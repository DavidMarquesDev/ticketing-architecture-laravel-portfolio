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
use App\Modules\Ticketing\Domain\Entities\Ticket;

$tests = [
    'create_ticket_command_handler_maps_command_to_input_dto' => static function (): void {
        $useCase = new FakeCreateTicketUseCase();
        $handler = new CreateTicketCommandHandler($useCase);

        $handler->handle(new CreateTicketCommand(77, 'Falha login', 'Usuário não consegue autenticar.'));
        $capturedInput = $useCase->capturedInput;

        assertSame(77, $capturedInput?->requesterId, 'Handler deve mapear requesterId.');
        assertSame('Falha login', $capturedInput?->title, 'Handler deve mapear title.');
        assertSame('Usuário não consegue autenticar.', $capturedInput?->description, 'Handler deve mapear description.');
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
