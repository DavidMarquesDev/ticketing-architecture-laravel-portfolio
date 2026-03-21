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
}
