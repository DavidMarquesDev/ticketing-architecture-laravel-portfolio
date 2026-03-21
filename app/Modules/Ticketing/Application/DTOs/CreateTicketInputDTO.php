<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

/**
 * DTO de entrada para abertura de ticket.
 *
 * @author David Marques
 */
final class CreateTicketInputDTO
{
    public function __construct(
        public readonly int $requesterId,
        public readonly string $title,
        public readonly string $description
    ) {
    }
}
