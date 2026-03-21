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
    private static array $tickets = [];

    public function save(Ticket $ticket): Ticket
    {
        self::$tickets[$ticket->id()] = $ticket;

        return $ticket;
    }

    public function list(int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        return array_values(array_slice(self::$tickets, $offset, $perPage, true));
    }

    public function findById(string $ticketId): ?Ticket
    {
        return self::$tickets[$ticketId] ?? null;
    }
}
