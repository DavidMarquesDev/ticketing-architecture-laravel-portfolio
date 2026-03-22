<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

use App\Modules\Ticketing\Domain\Entities\TicketComment;

interface TicketCommentRepositoryPort
{
    public function save(TicketComment $comment): TicketComment;
    public function listByTicketId(string $ticketId, int $page, int $perPage): array;
}
