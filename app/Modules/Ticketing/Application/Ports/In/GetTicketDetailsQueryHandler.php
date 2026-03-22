<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Queries\GetTicketDetailsQuery;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface GetTicketDetailsQueryHandler
{
    public function execute(GetTicketDetailsQuery $query): Ticket;
}
