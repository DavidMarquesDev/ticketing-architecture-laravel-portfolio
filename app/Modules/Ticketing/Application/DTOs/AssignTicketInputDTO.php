<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class AssignTicketInputDTO
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $assigneeId
    ) {
    }
}
