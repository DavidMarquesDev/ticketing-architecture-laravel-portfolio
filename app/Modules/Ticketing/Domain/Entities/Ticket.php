<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Entities;

use App\Modules\Ticketing\Domain\Enums\TicketStatus;
use App\Modules\Ticketing\Domain\Exceptions\TicketStateException;

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
        private ?int $assigneeId,
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
            assigneeId: null,
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

    public function assigneeId(): ?int
    {
        return $this->assigneeId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function assignTo(int $assigneeId): void
    {
        if ($this->status === TicketStatus::CLOSED) {
            throw new TicketStateException('Não é possível atribuir um ticket fechado.');
        }

        $this->assigneeId = $assigneeId;
        $this->status = TicketStatus::PENDING;
    }

    public function reply(): void
    {
        if ($this->status === TicketStatus::CLOSED) {
            throw new TicketStateException('Não é possível responder um ticket fechado.');
        }

        $this->status = TicketStatus::PENDING;
    }

    public function close(): void
    {
        if ($this->status === TicketStatus::CLOSED) {
            throw new TicketStateException('Ticket já está fechado.');
        }

        $this->status = TicketStatus::CLOSED;
    }
}
