<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Events;

final class TicketAssigned
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $assigneeId,
        public readonly int $actorUserId
    ) {
    }
}
