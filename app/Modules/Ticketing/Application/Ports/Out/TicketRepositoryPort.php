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

    /**
     * @return array<int, Ticket>
     */
    public function list(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): array;

    public function findById(string $ticketId): ?Ticket;
}
