<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Infrastructure\Persistence\Eloquent\TicketCommentModel;

final class EloquentTicketCommentRepository implements TicketCommentRepositoryPort
{
    public function save(TicketComment $comment): TicketComment
    {
        TicketCommentModel::query()->updateOrCreate(
            ['id' => $comment->id()],
            [
                'ticket_id' => $comment->ticketId(),
                'author_id' => $comment->authorId(),
                'message' => $comment->message(),
                'created_at' => date('Y-m-d H:i:s', strtotime($comment->createdAt())),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        return $comment;
    }

    public function listByTicketId(string $ticketId, int $page, int $perPage): array
    {
        $rows = TicketCommentModel::query()
            ->select(['id', 'ticket_id', 'author_id', 'message', 'created_at'])
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->forPage($page, $perPage)
            ->get();

        return array_values(
            array_map(
                static fn (TicketCommentModel $row): TicketComment => self::toDomain($row),
                $rows->all()
            )
        );
    }

    private static function toDomain(TicketCommentModel $model): TicketComment
    {
        $createdAt = $model->getAttribute('created_at');

        return new TicketComment(
            id: (string) $model->getAttribute('id'),
            ticketId: (string) $model->getAttribute('ticket_id'),
            authorId: (int) $model->getAttribute('author_id'),
            message: (string) $model->getAttribute('message'),
            createdAt: is_string($createdAt) ? $createdAt : date(DATE_ATOM, strtotime((string) $createdAt))
        );
    }
}
