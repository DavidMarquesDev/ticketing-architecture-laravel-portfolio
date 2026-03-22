<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class ListTicketsInputDTO
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $status = null,
        public readonly ?int $requesterId = null,
        public readonly ?int $assigneeId = null,
        public readonly ?string $search = null,
        public readonly string $sortBy = 'id',
        public readonly string $sortDir = 'desc'
    ) {
    }
}
