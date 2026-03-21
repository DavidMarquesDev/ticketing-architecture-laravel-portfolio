<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Entities;

use App\Modules\Ticketing\Domain\Enums\TicketStatus;

/**
 * Entidade de domínio para ticket.
 *
 * @author David Marques
 */
final class Ticket
{
    public function __construct(
        private readonly string $id,
        private readonly int $requesterId,
        private readonly string $title,
        private readonly string $description,
        private TicketStatus $status
    ) {
    }

    public static function open(
        string $id,
        int $requesterId,
        string $title,
        string $description
    ): self {
        return new self(
            id: $id,
            requesterId: $requesterId,
            title: $title,
            description: $description,
            status: TicketStatus::OPEN
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function requesterId(): int
    {
        return $this->requesterId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }
}
