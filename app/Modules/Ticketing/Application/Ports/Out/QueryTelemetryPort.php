<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface QueryTelemetryPort
{
    /**
     * @param array<string, int|string|null> $context
     */
    public function record(string $queryName, string $status, float $durationMs, array $context = []): void;
}
