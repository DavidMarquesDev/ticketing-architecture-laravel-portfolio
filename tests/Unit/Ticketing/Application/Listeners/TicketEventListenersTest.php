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

use App\Modules\Ticketing\Application\Listeners\HandleTicketClosed;
use App\Modules\Ticketing\Application\Listeners\HandleTicketCreated;
use App\Modules\Ticketing\Application\Listeners\HandleTicketReplied;
use App\Modules\Ticketing\Application\Jobs\PublishTicketAuditJob;
use App\Modules\Ticketing\Application\Jobs\PublishTicketLifecycleAuditJob;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Domain\Events\TicketClosed;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Events\TicketReplied;

$tests = [
    'handle_ticket_created_forgets_cache_and_dispatches_created_audit_job' => static function (): void {
        $cache = new FakeListenerTicketListCache();
        $queue = new FakeListenerQueueDispatcher();
        $listener = new HandleTicketCreated($cache, $queue);

        $listener->handle(new TicketCreated('t-listener-1', 10));

        assertTrue($cache->forgetCalled, 'Listener de criação deve invalidar cache.');
        assertTrue($queue->lastJob instanceof PublishTicketAuditJob, 'Listener de criação deve despachar PublishTicketAuditJob.');
        assertSame('t-listener-1', $queue->lastJob->ticketId, 'Job de criação deve conter ticketId.');
        assertSame(10, $queue->lastJob->requesterId, 'Job de criação deve conter requesterId.');
    },
    'handle_ticket_closed_forgets_cache_and_dispatches_lifecycle_job' => static function (): void {
        $cache = new FakeListenerTicketListCache();
        $queue = new FakeListenerQueueDispatcher();
        $listener = new HandleTicketClosed($cache, $queue);

        $listener->handle(new TicketClosed('t-listener-2'));

        assertTrue($cache->forgetCalled, 'Listener de fechamento deve invalidar cache.');
        assertTrue($queue->lastJob instanceof PublishTicketLifecycleAuditJob, 'Listener de fechamento deve despachar PublishTicketLifecycleAuditJob.');
        assertSame('t-listener-2', $queue->lastJob->ticketId, 'Job de fechamento deve conter ticketId.');
        assertSame('closed', $queue->lastJob->action, 'Job de fechamento deve conter action closed.');
        assertSame(null, $queue->lastJob->actorId, 'Job de fechamento não deve conter actorId.');
    },
    'handle_ticket_replied_forgets_cache_and_dispatches_lifecycle_job_with_actor' => static function (): void {
        $cache = new FakeListenerTicketListCache();
        $queue = new FakeListenerQueueDispatcher();
        $listener = new HandleTicketReplied($cache, $queue);

        $listener->handle(new TicketReplied('t-listener-3', 'c-listener-1', 88));

        assertTrue($cache->forgetCalled, 'Listener de resposta deve invalidar cache.');
        assertTrue($queue->lastJob instanceof PublishTicketLifecycleAuditJob, 'Listener de resposta deve despachar PublishTicketLifecycleAuditJob.');
        assertSame('t-listener-3', $queue->lastJob->ticketId, 'Job de resposta deve conter ticketId.');
        assertSame('replied', $queue->lastJob->action, 'Job de resposta deve conter action replied.');
        assertSame(88, $queue->lastJob->actorId, 'Job de resposta deve conter actorId.');
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

final class FakeListenerTicketListCache implements TicketListCachePort
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

final class FakeListenerQueueDispatcher implements QueueDispatcherPort
{
    public ?object $lastJob = null;

    public function dispatch(object $job): void
    {
        $this->lastJob = $job;
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
