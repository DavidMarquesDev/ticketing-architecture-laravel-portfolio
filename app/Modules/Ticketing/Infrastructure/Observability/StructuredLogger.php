<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Observability;

final class StructuredLogger
{
    public static function log(string $type, array $payload = []): void
    {
        $traceId = self::normalizeNullableString($payload['trace_id'] ?? null) ?? self::generateTraceId();
        $correlationId = self::normalizeNullableString($payload['correlation_id'] ?? null) ?? $traceId;

        $record = array_merge(
            [
                'module' => 'ticketing',
                'type' => $type,
                'trace_id' => $traceId,
                'correlation_id' => $correlationId,
            ],
            $payload
        );

        $record['trace_id'] = $traceId;
        $record['correlation_id'] = $correlationId;

        error_log(
            json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function generateTraceId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
