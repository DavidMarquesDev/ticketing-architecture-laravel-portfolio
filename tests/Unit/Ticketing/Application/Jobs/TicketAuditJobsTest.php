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

use App\Modules\Ticketing\Application\Jobs\PublishTicketAuditJob;
use App\Modules\Ticketing\Application\Jobs\PublishTicketIntegrationEventJob;
use App\Modules\Ticketing\Application\Jobs\PublishTicketLifecycleAuditJob;

$tests = [
    'publish_ticket_audit_job_logs_created_event' => static function (): void {
        $logPath = createTempLogPath('created');
        $previousLogPath = ini_get('error_log');
        ini_set('error_log', $logPath);

        try {
            $job = new PublishTicketAuditJob('t-job-1', 21);
            $job->handle();
            $logContent = (string) file_get_contents($logPath);

            assertTrue(str_contains($logContent, 'ticket.created.audit'), 'Job de criação deve registrar evento de auditoria.');
            assertTrue(str_contains($logContent, 'ticket_id=t-job-1'), 'Job de criação deve registrar ticket_id.');
            assertTrue(str_contains($logContent, 'requester_id=21'), 'Job de criação deve registrar requester_id.');
        } finally {
            ini_set('error_log', is_string($previousLogPath) ? $previousLogPath : '');
            removeFileIfExists($logPath);
        }
    },
    'publish_ticket_lifecycle_job_logs_closed_event_without_actor' => static function (): void {
        $logPath = createTempLogPath('closed');
        $previousLogPath = ini_get('error_log');
        ini_set('error_log', $logPath);

        try {
            $job = new PublishTicketLifecycleAuditJob('t-job-2', 'closed');
            $job->handle();
            $logContent = (string) file_get_contents($logPath);

            assertTrue(str_contains($logContent, 'ticket.lifecycle.audit'), 'Job de ciclo de vida deve registrar auditoria.');
            assertTrue(str_contains($logContent, 'action=closed'), 'Job de ciclo de vida deve registrar action.');
            assertTrue(str_contains($logContent, 'ticket_id=t-job-2'), 'Job de ciclo de vida deve registrar ticket_id.');
            assertTrue(str_contains($logContent, 'actor_id=null'), 'Job de ciclo de vida sem ator deve registrar actor_id null.');
        } finally {
            ini_set('error_log', is_string($previousLogPath) ? $previousLogPath : '');
            removeFileIfExists($logPath);
        }
    },
    'publish_ticket_lifecycle_job_logs_replied_event_with_actor' => static function (): void {
        $logPath = createTempLogPath('replied');
        $previousLogPath = ini_get('error_log');
        ini_set('error_log', $logPath);

        try {
            $job = new PublishTicketLifecycleAuditJob('t-job-3', 'replied', 99);
            $job->handle();
            $logContent = (string) file_get_contents($logPath);

            assertTrue(str_contains($logContent, 'action=replied'), 'Job de reply deve registrar action replied.');
            assertTrue(str_contains($logContent, 'ticket_id=t-job-3'), 'Job de reply deve registrar ticket_id.');
            assertTrue(str_contains($logContent, 'actor_id=99'), 'Job de reply deve registrar actor_id.');
        } finally {
            ini_set('error_log', is_string($previousLogPath) ? $previousLogPath : '');
            removeFileIfExists($logPath);
        }
    },
    'publish_ticket_integration_event_job_logs_event_name_and_payload' => static function (): void {
        $logPath = createTempLogPath('integration');
        $previousLogPath = ini_get('error_log');
        ini_set('error_log', $logPath);

        try {
            $job = new PublishTicketIntegrationEventJob(
                'ticket.replied.v1',
                [
                    'ticket_id' => 't-job-4',
                    'comment_id' => 'c-job-1',
                    'author_id' => 77,
                ]
            );
            $job->handle();
            $logContent = (string) file_get_contents($logPath);

            assertTrue(str_contains($logContent, 'ticket.integration.event'), 'Job de integração deve registrar tipo de log esperado.');
            assertTrue(str_contains($logContent, 'name=ticket.replied.v1'), 'Job de integração deve registrar nome do evento.');
            assertTrue(str_contains($logContent, '"ticket_id":"t-job-4"'), 'Job de integração deve registrar ticket_id no payload.');
            assertTrue(str_contains($logContent, '"comment_id":"c-job-1"'), 'Job de integração deve registrar comment_id no payload.');
            assertTrue(str_contains($logContent, '"author_id":77'), 'Job de integração deve registrar author_id no payload.');
        } finally {
            ini_set('error_log', is_string($previousLogPath) ? $previousLogPath : '');
            removeFileIfExists($logPath);
        }
    },
];

function createTempLogPath(string $suffix): string
{
    $directory = sys_get_temp_dir();
    $path = $directory . DIRECTORY_SEPARATOR . 'ticketing-job-test-' . $suffix . '-' . uniqid('', true) . '.log';
    file_put_contents($path, '');

    return $path;
}

function removeFileIfExists(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
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
