<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Domain\Entities\TicketComment;

interface ReplyTicketUseCase
{
    public function execute(ReplyTicketInputDTO $input): TicketComment;
}
