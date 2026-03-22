<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\Ticket;
use App\Modules\Ticketing\Domain\Enums\TicketStatus;
use App\Modules\Ticketing\Infrastructure\Persistence\Eloquent\TicketModel;
use stdClass;

final class EloquentTicketRepository implements TicketRepositoryPort
{
    public function save(Ticket $ticket): Ticket
    {
        TicketModel::query()->updateOrCreate(
            ['id' => $ticket->id()],
            [
                'requester_id' => $ticket->requesterId(),
                'assignee_id' => $ticket->assigneeId(),
                'status' => $ticket->status()->value,
                'title' => $ticket->title(),
                'description' => $ticket->description(),
                'last_reply_at' => $ticket->status() === TicketStatus::PENDING ? date('Y-m-d H:i:s') : null,
                'closed_at' => $ticket->status() === TicketStatus::CLOSED ? date('Y-m-d H:i:s') : null,
            ]
        );

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
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $searchTerm = $search !== null ? trim($search) : null;
        $query = TicketModel::query()->select([
            'id',
            'requester_id',
            'assignee_id',
            'status',
            'title',
            'description',
        ]);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($requesterId !== null) {
            $query->where('requester_id', $requesterId);
        }

        if ($assigneeId !== null) {
            $query->where('assignee_id', $assigneeId);
        }

        if ($searchTerm !== null && $searchTerm !== '') {
            $needle = '%' . $searchTerm . '%';
            $query->where(
                static function (object $builder) use ($needle): void {
                    if (method_exists($builder, 'where') && method_exists($builder, 'orWhere')) {
                        $builder->where('title', 'like', $needle)->orWhere('description', 'like', $needle);
                    }
                }
            );
        }

        $allowedSorts = [
            'id' => 'id',
            'status' => 'status',
            'title' => 'title',
            'requester_id' => 'requester_id',
            'assignee_id' => 'assignee_id',
        ];
        $sortColumn = $allowedSorts[$sortBy] ?? 'id';
        $direction = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        $rows = $query
            ->orderBy($sortColumn, $direction)
            ->forPage($page, $perPage)
            ->toBase()
            ->get();

        return array_values(
            array_map(
                static fn (stdClass $row): Ticket => self::toDomainFromRow($row),
                $rows->all()
            )
        );
    }

    public function findById(string $ticketId): ?Ticket
    {
        $row = TicketModel::query()
            ->select(['id', 'requester_id', 'assignee_id', 'status', 'title', 'description'])
            ->where('id', $ticketId)
            ->first();

        if (!$row instanceof TicketModel) {
            return null;
        }

        return self::toDomain($row);
    }

    private static function toDomain(TicketModel $model): Ticket
    {
        return new Ticket(
            id: (string) $model->getAttribute('id'),
            requesterId: (int) $model->getAttribute('requester_id'),
            assigneeId: ($model->getAttribute('assignee_id') !== null) ? (int) $model->getAttribute('assignee_id') : null,
            title: (string) $model->getAttribute('title'),
            description: (string) $model->getAttribute('description'),
            status: TicketStatus::from((string) $model->getAttribute('status'))
        );
    }

    private static function toDomainFromRow(stdClass $row): Ticket
    {
        return new Ticket(
            id: (string) ($row->id ?? ''),
            requesterId: (int) ($row->requester_id ?? 0),
            assigneeId: isset($row->assignee_id) ? (int) $row->assignee_id : null,
            title: (string) ($row->title ?? ''),
            description: (string) ($row->description ?? ''),
            status: TicketStatus::from((string) ($row->status ?? TicketStatus::OPEN->value))
        );
    }
}
