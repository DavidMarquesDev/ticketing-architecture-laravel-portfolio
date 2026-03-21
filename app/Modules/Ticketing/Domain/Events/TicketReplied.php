<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Events;

final class TicketReplied
{
    public function __construct(
        public readonly string $ticketId,
        public readonly string $commentId,
        public readonly int $authorId
    ) {
    }
}
