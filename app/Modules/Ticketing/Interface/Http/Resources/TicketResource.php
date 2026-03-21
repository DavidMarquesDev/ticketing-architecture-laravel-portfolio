<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Resources;

/**
 * Resource HTTP para retorno de ticket.
 *
 * @author David Marques
 */
final class TicketResource
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
            'requester_id' => $this->resource->requesterId(),
            'assignee_id' => $this->resource->assigneeId(),
            'title' => $this->resource->title(),
            'description' => $this->resource->description(),
            'status' => $this->resource->status()->value,
        ];
    }
}
