<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Commands\CreateTicketCommand;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface CreateTicketCommandHandler
{
    public function handle(CreateTicketCommand $command): Ticket;
}
