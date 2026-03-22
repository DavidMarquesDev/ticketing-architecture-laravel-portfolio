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

use App\Modules\Ticketing\Application\CommandHandlers\ReplyTicketCommandHandler;
use App\Modules\Ticketing\Application\Commands\ReplyTicketCommand;
use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Domain\Entities\TicketComment;

$tests = [
    'reply_ticket_command_handler_maps_command_to_input_dto' => static function (): void {
        $useCase = new FakeReplyTicketUseCase();
        $handler = new ReplyTicketCommandHandler($useCase);

        $handler->handle(new ReplyTicketCommand('t-reply-handler-1', 77, 'Mensagem de teste'));
        $capturedInput = $useCase->capturedInput;

        assertSame('t-reply-handler-1', $capturedInput?->ticketId, 'Handler deve mapear ticketId.');
        assertSame(77, $capturedInput?->authorId, 'Handler deve mapear authorId.');
        assertSame('Mensagem de teste', $capturedInput?->message, 'Handler deve mapear message.');
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

final class FakeReplyTicketUseCase implements ReplyTicketUseCase
{
    public ?ReplyTicketInputDTO $capturedInput = null;

    public function execute(ReplyTicketInputDTO $input): TicketComment
    {
        $this->capturedInput = $input;

        return TicketComment::create('c-reply-handler-1', $input->ticketId, $input->authorId, $input->message);
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
