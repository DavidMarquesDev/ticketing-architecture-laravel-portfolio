<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource HTTP para retorno de ticket.
 *
 * @author David Marques
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'requester_id' => $this->resource->requesterId(),
            'title' => $this->resource->title(),
            'description' => $this->resource->description(),
            'status' => $this->resource->status()->value,
        ];
    }
}
