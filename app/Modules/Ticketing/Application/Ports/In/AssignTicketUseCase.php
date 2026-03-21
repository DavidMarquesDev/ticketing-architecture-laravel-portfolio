<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\AssignTicketInputDTO;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface AssignTicketUseCase
{
    public function execute(AssignTicketInputDTO $input): Ticket;
}
