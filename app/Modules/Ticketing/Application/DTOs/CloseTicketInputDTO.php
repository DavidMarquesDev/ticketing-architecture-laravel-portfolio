<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class CloseTicketInputDTO
{
    public function __construct(
        public readonly string $ticketId
    ) {
    }
}
