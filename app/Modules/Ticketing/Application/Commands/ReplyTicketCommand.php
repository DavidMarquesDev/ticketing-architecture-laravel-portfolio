<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Commands;

final class ReplyTicketCommand
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $authorId,
        public readonly string $message,
        public readonly ?string $traceId = null
    ) {
    }
}
