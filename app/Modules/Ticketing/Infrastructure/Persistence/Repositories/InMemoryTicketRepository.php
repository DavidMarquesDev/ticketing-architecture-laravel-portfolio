<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;

/**
 * Implementação inicial de repositório para evolução da arquitetura.
 *
 * @author David Marques
 */
final class InMemoryTicketRepository implements TicketRepositoryPort
{
    /**
     * @var array<string, Ticket>
     */
    private array $tickets = [];

    public function save(Ticket $ticket): Ticket
    {
        $this->tickets[$ticket->id()] = $ticket;

        return $ticket;
    }
}
