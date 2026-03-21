<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Entities;

final class TicketComment
{
    public function __construct(
        private readonly string $id,
        private readonly string $ticketId,
        private readonly int $authorId,
        private readonly string $message,
        private readonly string $createdAt
    ) {
    }

    public static function create(
        string $id,
        string $ticketId,
        int $authorId,
        string $message
    ): self {
        return new self(
            id: $id,
            ticketId: $ticketId,
            authorId: $authorId,
            message: $message,
            createdAt: date(DATE_ATOM)
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function ticketId(): string
    {
        return $this->ticketId;
    }

    public function authorId(): int
    {
        return $this->authorId;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }
}
