<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\Commands\ReplyTicketCommand;
use App\Modules\Ticketing\Domain\Entities\TicketComment;

interface ReplyTicketCommandHandler
{
    public function handle(ReplyTicketCommand $command): TicketComment;
}
