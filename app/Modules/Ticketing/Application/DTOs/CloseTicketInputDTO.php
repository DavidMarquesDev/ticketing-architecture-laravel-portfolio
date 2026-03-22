<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class CloseTicketInputDTO
{
    public function __construct(
        public readonly string $ticketId,
        public readonly int $actorUserId
    ) {
    }

    public function ticketId(): string
    {
        return $this->ticketId;
    }

    public function actorUserId(): int
    {
        return $this->actorUserId;
    }
}
