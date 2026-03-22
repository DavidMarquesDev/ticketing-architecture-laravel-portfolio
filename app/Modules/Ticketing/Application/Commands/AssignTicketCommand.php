<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Commands;

final class AssignTicketCommand
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $assigneeId,
        public readonly int $actorUserId
    ) {
    }
}
