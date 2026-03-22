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

use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Queries\ListTicketCommentsQuery;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketCommentsQueryHandler;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

$tests = [
    'list_ticket_comments_returns_comments_when_ticket_exists' => static function (): void {
        $ticket = Ticket::open('t-comments-1', 10, 'Erro checkout', 'Falha 500');
        $ticketRepository = new FakeTicketRepository([$ticket->id() => $ticket]);
        $commentOne = TicketComment::create('c-1', 't-comments-1', 99, 'Mensagem 1');
        $commentTwo = TicketComment::create('c-2', 't-comments-1', 99, 'Mensagem 2');
        $commentRepository = new FakeTicketCommentRepository([$commentOne, $commentTwo]);
        $telemetry = new FakeQueryTelemetry();
        $handler = new ListTicketCommentsQueryHandler($ticketRepository, $commentRepository, $telemetry);

        $result = $handler->execute(new ListTicketCommentsQuery('t-comments-1', 1, 10));

        assertSame(2, count($result), 'Handler deve retornar comentários do ticket.');
        assertSame('c-1', $result[0]->id(), 'Primeiro comentário deve manter ordenação.');
        assertSame('t-comments-1', $commentRepository->lastTicketId, 'Repositório deve receber ticketId da query.');
        assertSame(1, $commentRepository->lastPage, 'Repositório deve receber página da query.');
        assertSame(10, $commentRepository->lastPerPage, 'Repositório deve receber perPage da query.');
        assertSame('list_ticket_comments', $telemetry->lastQueryName, 'Handler deve registrar nome da query.');
        assertSame('success', $telemetry->lastStatus, 'Handler deve registrar sucesso.');
    },
    'list_ticket_comments_throws_not_found_when_ticket_does_not_exist' => static function (): void {
        $ticketRepository = new FakeTicketRepository([]);
        $commentRepository = new FakeTicketCommentRepository([]);
        $telemetry = new FakeQueryTelemetry();
        $handler = new ListTicketCommentsQueryHandler($ticketRepository, $commentRepository, $telemetry);

        assertThrows(
            static fn (): array => $handler->execute(new ListTicketCommentsQuery('t-missing', 1, 10)),
            TicketNotFoundException::class,
            'Handler deve lançar TicketNotFoundException quando ticket não existir.'
        );
        assertSame('failure', $telemetry->lastStatus, 'Handler deve registrar falha quando ticket não existir.');
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

final class FakeTicketRepository implements TicketRepositoryPort
{
    public function __construct(
        public array $tickets
    ) {
    }

    public function save(Ticket $ticket): Ticket
    {
        $this->tickets[$ticket->id()] = $ticket;

        return $ticket;
    }

    public function list(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): array
    {
        return array_values($this->tickets);
    }

    public function findById(string $ticketId): ?Ticket
    {
        $ticket = $this->tickets[$ticketId] ?? null;

        return $ticket instanceof Ticket ? $ticket : null;
    }
}

final class FakeTicketCommentRepository implements TicketCommentRepositoryPort
{
    public string $lastTicketId = '';

    public int $lastPage = 0;

    public int $lastPerPage = 0;

    public function __construct(
        private array $comments
    ) {
    }

    public function save(TicketComment $comment): TicketComment
    {
        return $comment;
    }

    public function listByTicketId(string $ticketId, int $page, int $perPage): array
    {
        $this->lastTicketId = $ticketId;
        $this->lastPage = $page;
        $this->lastPerPage = $perPage;

        $filteredComments = array_filter(
            $this->comments,
            static fn (TicketComment $comment): bool => $comment->ticketId() === $ticketId
        );

        return array_values($filteredComments);
    }
}

final class FakeQueryTelemetry implements QueryTelemetryPort
{
    public string $lastQueryName = '';

    public string $lastStatus = '';

    public float $lastDurationMs = 0.0;

    public function record(string $queryName, string $status, float $durationMs, array $context = []): void
    {
        $this->lastQueryName = $queryName;
        $this->lastStatus = $status;
        $this->lastDurationMs = $durationMs;
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
