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

    public function list(
        int $page,
        int $perPage,
        ?string $status = null,
        ?int $requesterId = null,
        ?int $assigneeId = null,
        ?string $search = null,
        string $sortBy = 'id',
        string $sortDir = 'desc'
    ): array
    {
        $tickets = array_values(self::$tickets);
        $filtered = array_values(
            array_filter(
                $tickets,
                static function (Ticket $ticket) use ($status, $requesterId, $assigneeId, $search): bool {
                    if ($status !== null && $ticket->status()->value !== $status) {
                        return false;
                    }

                    if ($requesterId !== null && $ticket->requesterId() !== $requesterId) {
                        return false;
                    }

                    if ($assigneeId !== null && $ticket->assigneeId() !== $assigneeId) {
                        return false;
                    }

                    if ($search !== null) {
                        $needle = strtolower(trim($search));
                        $title = strtolower($ticket->title());
                        $description = strtolower($ticket->description());

                        return str_contains($title, $needle) || str_contains($description, $needle);
                    }

                    return true;
                }
            )
        );
        usort(
            $filtered,
            static function (Ticket $left, Ticket $right) use ($sortBy, $sortDir): int {
                $comparison = match ($sortBy) {
                    'status' => strcmp($left->status()->value, $right->status()->value),
                    'title' => strcmp($left->title(), $right->title()),
                    'requester_id' => $left->requesterId() <=> $right->requesterId(),
                    'assignee_id' => ($left->assigneeId() ?? 0) <=> ($right->assigneeId() ?? 0),
                    default => strcmp($left->id(), $right->id()),
                };

                return $sortDir === 'asc' ? $comparison : $comparison * -1;
            }
        );
        $offset = max(0, ($page - 1) * $perPage);

        return array_values(array_slice($filtered, $offset, $perPage));
    }

    public function findById(string $ticketId): ?Ticket
    {
        return self::$tickets[$ticketId] ?? null;
    }
}
