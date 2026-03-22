<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;
use App\Modules\Ticketing\Domain\Entities\Ticket;

interface ListTicketsUseCase
{
    /**
     * @return array<int, Ticket>
     */
    public function execute(ListTicketsInputDTO $input): array;
}
