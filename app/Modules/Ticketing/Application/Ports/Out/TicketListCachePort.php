<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

use App\Modules\Ticketing\Domain\Entities\Ticket;

interface TicketListCachePort
{
    /**
     * @return array<int, Ticket>|null
     */
    public function get(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): ?array;

    /**
     * @param array<int, Ticket> $tickets
     */
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
    ): void;

    public function forgetAll(): void;
}
