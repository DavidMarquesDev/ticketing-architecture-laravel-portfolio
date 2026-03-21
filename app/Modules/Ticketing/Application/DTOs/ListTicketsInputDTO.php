<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class ListTicketsInputDTO
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage
    ) {
    }
}
