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

use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\ReplyTicketService;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Domain\Events\TicketReplied;
use App\Modules\Ticketing\Domain\Enums\TicketStatus;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use App\Modules\Ticketing\Domain\Exceptions\TicketStateException;

$tests = [
    'reply_ticket_success' => static function (): void {
        $ticket = Ticket::open('t-2', 20, 'Erro login', 'Token inválido');

        $ticketRepository = new ReplyFakeTicketRepository([$ticket->id() => $ticket]);
        $commentRepository = new ReplyFakeTicketCommentRepository();
        $cache = new ReplyFakeTicketListCache();
        $lock = new ReplyFakeLock();
        $dispatcher = new ReplyFakeEventDispatcher();

        $service = new ReplyTicketService($ticketRepository, $commentRepository, $cache, $lock, $dispatcher);
        $comment = $service->execute(new ReplyTicketInputDTO('t-2', 99, 'Aplicada correção.'));

        assertSame('t-2', $comment->ticketId(), 'Comentário deve apontar para o ticket.');
        assertSame(99, $comment->authorId(), 'Comentário deve manter autor.');
        assertSame('Aplicada correção.', $comment->message(), 'Comentário deve manter mensagem.');
        assertSame(TicketStatus::PENDING, $ticketRepository->tickets['t-2']->status(), 'Ticket deve ficar como PENDING após resposta.');
        assertTrue($cache->forgetCalled, 'Cache deve ser invalidado na resposta.');
        assertTrue(isset($dispatcher->events[0]) && $dispatcher->events[0] instanceof TicketReplied, 'Evento TicketReplied deve ser disparado.');
    },
    'reply_ticket_not_found' => static function (): void {
        $ticketRepository = new ReplyFakeTicketRepository([]);
        $commentRepository = new ReplyFakeTicketCommentRepository();
        $cache = new ReplyFakeTicketListCache();
        $lock = new ReplyFakeLock();
        $dispatcher = new ReplyFakeEventDispatcher();
        $service = new ReplyTicketService($ticketRepository, $commentRepository, $cache, $lock, $dispatcher);

        expectException(
            static fn (): mixed => $service->execute(new ReplyTicketInputDTO('inexistente', 99, 'teste')),
            TicketNotFoundException::class
        );
    },
    'reply_ticket_state_conflict' => static function (): void {
        $ticket = Ticket::open('t-3', 22, 'Erro checkout', 'Falha 500');
        $ticket->close();

        $ticketRepository = new ReplyFakeTicketRepository([$ticket->id() => $ticket]);
        $commentRepository = new ReplyFakeTicketCommentRepository();
        $cache = new ReplyFakeTicketListCache();
        $lock = new ReplyFakeLock();
        $dispatcher = new ReplyFakeEventDispatcher();
        $service = new ReplyTicketService($ticketRepository, $commentRepository, $cache, $lock, $dispatcher);

        expectException(
            static fn (): mixed => $service->execute(new ReplyTicketInputDTO('t-3', 99, 'teste')),
            TicketStateException::class
        );
    },
];

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

function expectException(callable $callback, string $expectedException): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $expectedException) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Exceção diferente da esperada. Esperada: %s. Recebida: %s.',
                $expectedException,
                $throwable::class
            )
        );
    }

    throw new RuntimeException(sprintf('Exceção esperada não foi lançada: %s.', $expectedException));
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

final class ReplyFakeTicketRepository implements TicketRepositoryPort
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

final class ReplyFakeTicketCommentRepository implements TicketCommentRepositoryPort
{
    public array $comments = [];

    public function save(TicketComment $comment): TicketComment
    {
        $this->comments[$comment->id()] = $comment;

        return $comment;
    }

    public function listByTicketId(string $ticketId, int $page, int $perPage): array
    {
        $filteredComments = array_filter(
            $this->comments,
            static fn (TicketComment $comment): bool => $comment->ticketId() === $ticketId
        );

        return array_values($filteredComments);
    }
}

final class ReplyFakeTicketListCache implements TicketListCachePort
{
    public bool $forgetCalled = false;

    public function get(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): ?array
    {
        return null;
    }

    public function put(
        int $page,
        int $perPage,
        array $tickets,
        int $seconds,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): void
    {
    }

    public function forgetAll(): void
    {
        $this->forgetCalled = true;
    }
}

final class ReplyFakeLock implements DistributedLockPort
{
    public string $lastKey = '';

    public int $lastSeconds = 0;

    public function execute(string $key, int $seconds, callable $callback): mixed
    {
        $this->lastKey = $key;
        $this->lastSeconds = $seconds;

        return $callback();
    }
}

final class ReplyFakeEventDispatcher implements EventDispatcherPort
{
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
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
