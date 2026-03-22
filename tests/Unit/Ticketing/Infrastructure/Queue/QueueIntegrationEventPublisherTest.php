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

use App\Modules\Ticketing\Application\Jobs\PublishTicketIntegrationEventJob;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Infrastructure\Queue\QueueIntegrationEventPublisher;

$tests = [
    'queue_integration_event_publisher_dispatches_publish_ticket_integration_event_job' => static function (): void {
        $queue = new FakeQueueDispatcher();
        $publisher = new QueueIntegrationEventPublisher($queue);

        $publisher->publish(
            eventName: 'ticket.created.v1',
            payload: [
                'ticket_id' => 't-integration-1',
                'requester_id' => 55,
            ]
        );

        assertTrue($queue->lastJob instanceof PublishTicketIntegrationEventJob, 'Publisher deve despachar PublishTicketIntegrationEventJob.');
        assertSame('ticket.created.v1', $queue->lastJob->eventName, 'Publisher deve preservar nome do evento.');
        assertSame('t-integration-1', $queue->lastJob->payload['ticket_id'] ?? null, 'Publisher deve preservar ticket_id no payload.');
        assertSame(55, $queue->lastJob->payload['requester_id'] ?? null, 'Publisher deve preservar requester_id no payload.');
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

final class FakeQueueDispatcher implements QueueDispatcherPort
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
