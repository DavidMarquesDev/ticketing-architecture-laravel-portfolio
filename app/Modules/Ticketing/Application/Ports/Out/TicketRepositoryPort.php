<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

use App\Modules\Ticketing\Domain\Entities\Ticket;

/**
 * Contrato de persistência para tickets.
 *
 * @author David Marques
 */
interface TicketRepositoryPort
{
    public function save(Ticket $ticket): Ticket;
}
