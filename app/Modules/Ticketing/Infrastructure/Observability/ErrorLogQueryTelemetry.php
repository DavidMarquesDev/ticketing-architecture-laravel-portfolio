<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Observability;

use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;

final class ErrorLogQueryTelemetry implements QueryTelemetryPort
{
    public function record(string $queryName, string $status, float $durationMs, array $context = []): void
    {
        StructuredLogger::log(
            type: 'query_telemetry',
            payload: [
                'query' => $queryName,
                'status' => $status,
                'duration_ms' => round($durationMs, 3),
                'context' => $context,
                'trace_id' => is_string($context['trace_id'] ?? null) ? $context['trace_id'] : null,
                'correlation_id' => is_string($context['correlation_id'] ?? null) ? $context['correlation_id'] : null,
            ]
        );
    }
}
