<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Observability;

use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;

final class ErrorLogQueryTelemetry implements QueryTelemetryPort
{
    public function record(string $queryName, string $status, float $durationMs, array $context = []): void
    {
        error_log(
            json_encode(
                [
                    'module' => 'ticketing',
                    'type' => 'query_telemetry',
                    'query' => $queryName,
                    'status' => $status,
                    'duration_ms' => round($durationMs, 3),
                    'context' => $context,
                ],
                JSON_UNESCAPED_UNICODE
            ) ?: ''
        );
    }
}
