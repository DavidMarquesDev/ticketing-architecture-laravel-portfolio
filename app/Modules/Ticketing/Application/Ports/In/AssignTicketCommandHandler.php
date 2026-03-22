<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Commands\AssignTicketCommand;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface AssignTicketCommandHandler
{
    public function handle(AssignTicketCommand $command): Ticket;
}
