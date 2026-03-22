<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Commands;

final class CloseTicketCommand
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $actorUserId,
        public readonly ?string $traceId = null
    ) {
    }
}
