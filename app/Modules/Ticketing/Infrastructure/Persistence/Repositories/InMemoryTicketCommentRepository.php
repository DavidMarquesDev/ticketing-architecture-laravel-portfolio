<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\TicketComment;

final class InMemoryTicketCommentRepository implements TicketCommentRepositoryPort
{
    private static array $comments = [];

    public function save(TicketComment $comment): TicketComment
    {
        self::$comments[$comment->id()] = $comment;

        return $comment;
    }

    public function listByTicketId(string $ticketId, int $page, int $perPage): array
    {
        $filteredComments = array_filter(
            self::$comments,
            static fn (TicketComment $comment): bool => $comment->ticketId() === $ticketId
        );

        $offset = max(0, ($page - 1) * $perPage);

        return array_values(array_slice($filteredComments, $offset, $perPage, true));
    }
}
