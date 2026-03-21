<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\ListTicketsInputDTO;

interface ListTicketsUseCase
{
    public function execute(ListTicketsInputDTO $input): array;
}
