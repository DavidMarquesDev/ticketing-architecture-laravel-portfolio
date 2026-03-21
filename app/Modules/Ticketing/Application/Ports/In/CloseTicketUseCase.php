<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\CloseTicketInputDTO;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface CloseTicketUseCase
{
    public function execute(CloseTicketInputDTO $input): Ticket;
}
