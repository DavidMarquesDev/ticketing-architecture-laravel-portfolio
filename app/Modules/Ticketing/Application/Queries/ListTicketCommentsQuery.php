<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Queries;

final class ListTicketCommentsQuery
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $page,
        public readonly int $perPage
    ) {
    }
}
