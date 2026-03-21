<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Events;

final class TicketCreated
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $requesterId
    ) {
    }
}
