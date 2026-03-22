<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Commands\CloseTicketCommand;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface CloseTicketCommandHandler
{
    public function handle(CloseTicketCommand $command): Ticket;
}
