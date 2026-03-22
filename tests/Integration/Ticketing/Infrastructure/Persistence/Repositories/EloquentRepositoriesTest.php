<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__, 7) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (!class_exists('Illuminate\Database\Capsule\Manager')) {
    echo "SKIP: Illuminate Database indisponível.\n";

    return;
}

use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\EloquentTicketCommentRepository;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\EloquentTicketRepository;
$managerClass = 'Illuminate\Database\Capsule\Manager';
$capsule = new $managerClass();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = $capsule->schema();
$schema->create('tickets', static function ($table): void {
    $table->string('id')->primary();
    $table->unsignedBigInteger('requester_id');
    $table->unsignedBigInteger('assignee_id')->nullable();
    $table->string('status', 20);
    $table->string('title', 180);
    $table->text('description');
    $table->timestamp('last_reply_at')->nullable();
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});
$schema->create('ticket_comments', static function ($table): void {
    $table->string('id')->primary();
    $table->string('ticket_id');
    $table->unsignedBigInteger('author_id');
    $table->text('message');
    $table->timestamps();
});

$tests = [
    'eloquent_ticket_repository_save_find_and_list' => static function (): void {
        $repository = new EloquentTicketRepository();

        $openTicket = Ticket::open('t-sql-1', 10, 'Checkout lento', 'Descrição A');
        $pendingTicket = Ticket::open('t-sql-2', 10, 'Checkout pendente', 'Descrição B');
        $pendingTicket->assignTo(77);
        $repository->save($openTicket);
        $repository->save($pendingTicket);

        $found = $repository->findById('t-sql-2');
        assertTrue($found !== null, 'Repositório Eloquent deve localizar ticket salvo por id.');
        assertSame('t-sql-2', $found?->id(), 'Ticket encontrado deve possuir o id esperado.');

        $listed = $repository->list(
            page: 1,
            perPage: 10,
            status: 'pending',
            requesterId: 10,
            assigneeId: 77,
            search: 'Checkout',
            sortBy: 'title',
            sortDir: 'asc'
        );
        assertSame(1, count($listed), 'Listagem Eloquent deve aplicar filtros combinados.');
        assertSame('t-sql-2', $listed[0]->id(), 'Listagem Eloquent deve retornar ticket filtrado.');
    },
    'eloquent_ticket_comment_repository_save_and_list' => static function (): void {
        $ticketRepository = new EloquentTicketRepository();
        $commentRepository = new EloquentTicketCommentRepository();
        $ticketRepository->save(Ticket::open('t-sql-comments', 21, 'Assunto', 'Descrição'));

        $first = TicketComment::create('c-sql-1', 't-sql-comments', 21, 'Primeira mensagem');
        $second = TicketComment::create('c-sql-2', 't-sql-comments', 22, 'Segunda mensagem');
        $commentRepository->save($first);
        $commentRepository->save($second);

        $listed = $commentRepository->listByTicketId('t-sql-comments', 1, 10);
        assertSame(2, count($listed), 'Listagem de comentários Eloquent deve retornar itens salvos.');
        assertSame('c-sql-1', $listed[0]->id(), 'Listagem de comentários deve manter ordem por created_at.');
        assertSame('c-sql-2', $listed[1]->id(), 'Listagem de comentários deve manter ordem por created_at.');
    },
];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[OK] {$name}\n";
    } catch (Throwable $throwable) {
        fwrite(STDERR, "[FAIL] {$name}: {$throwable->getMessage()}\n");
        exit(1);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
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
