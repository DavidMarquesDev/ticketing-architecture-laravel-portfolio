<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Resources;

/**
 * Resource HTTP para retorno de comentário de ticket.
 *
 * @author David Marques
 */
final class TicketCommentResource
{
    public function __construct(
        private readonly object $resource
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $request = null): array
    {
        return [
            'id' => $this->resource->id(),
            'ticket_id' => $this->resource->ticketId(),
            'author_id' => $this->resource->authorId(),
            'message' => $this->resource->message(),
            'created_at' => $this->resource->createdAt(),
        ];
    }
}
