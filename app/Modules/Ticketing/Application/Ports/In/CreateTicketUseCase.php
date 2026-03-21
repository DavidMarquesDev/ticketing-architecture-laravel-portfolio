<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\CreateTicketInputDTO;
use App\Modules\Ticketing\Domain\Entities\Ticket;

/**
 * Contrato de caso de uso para criação de ticket.
 *
 * @author David Marques
 */
interface CreateTicketUseCase
{
    public function execute(CreateTicketInputDTO $input): Ticket;
}
