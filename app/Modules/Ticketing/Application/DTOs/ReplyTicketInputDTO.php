<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class ReplyTicketInputDTO
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $authorId,
        public readonly string $message
    ) {
    }
}
