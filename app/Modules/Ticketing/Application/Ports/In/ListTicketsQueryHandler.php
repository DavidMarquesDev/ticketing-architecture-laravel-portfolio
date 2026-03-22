<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Queries\ListTicketsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface ListTicketsQueryHandler
{
    /**
     * @return array<int, Ticket>
     */
    public function execute(ListTicketsQuery $query): array;
}
