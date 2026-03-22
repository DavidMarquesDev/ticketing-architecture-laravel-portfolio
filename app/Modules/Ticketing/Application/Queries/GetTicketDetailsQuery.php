<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Queries;

final class GetTicketDetailsQuery
{
    public function __construct(
        public readonly string $ticketId
    ) {
    }
}
