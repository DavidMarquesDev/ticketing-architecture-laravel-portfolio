<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Queries\ListTicketCommentsQuery;
use App\Modules\Ticketing\Domain\Entities\TicketComment;

interface ListTicketCommentsUseCase
{
    public function execute(ListTicketCommentsQuery $query): array;
}
