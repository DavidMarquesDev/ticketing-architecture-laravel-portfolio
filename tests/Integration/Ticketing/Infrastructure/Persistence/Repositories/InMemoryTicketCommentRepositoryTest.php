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

use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\InMemoryTicketCommentRepository;

$tests = [
    'inmemory_ticket_comment_repository_save' => static function (): void {
        resetRepositoryState();
        $repository = new InMemoryTicketCommentRepository();
        $comment = TicketComment::create('c-1', 't-1', 99, 'Aplicada correção.');

        $saved = $repository->save($comment);
        $storedComments = getStoredComments();

        assertSame($comment, $saved, 'Save deve retornar a instância persistida.');
        assertSame($comment, $storedComments['c-1'] ?? null, 'Comentário deve ser armazenado com o id correto.');
    },
    'inmemory_ticket_comment_repository_overwrite_same_id' => static function (): void {
        resetRepositoryState();
        $repository = new InMemoryTicketCommentRepository();
        $original = TicketComment::create('c-1', 't-1', 99, 'Mensagem original.');
        $updated = TicketComment::create('c-1', 't-1', 99, 'Mensagem atualizada.');

        $repository->save($original);
        $repository->save($updated);
        $storedComments = getStoredComments();

        assertSame(1, count($storedComments), 'Persistência com mesmo id deve manter apenas um registro.');
        assertSame('Mensagem atualizada.', $storedComments['c-1']->message(), 'Registro final deve refletir último save.');
    },
];

function resetRepositoryState(): void
{
    $reflection = new ReflectionClass(InMemoryTicketCommentRepository::class);
    $property = $reflection->getProperty('comments');
    $property->setValue(null, []);
}

function getStoredComments(): array
{
    $reflection = new ReflectionClass(InMemoryTicketCommentRepository::class);
    $property = $reflection->getProperty('comments');
    $value = $property->getValue();

    return is_array($value) ? $value : [];
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
