<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Commands;

final class CreateTicketCommand
{
    public function __construct(
        public readonly int $requesterId,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $traceId = null
    ) {
    }
}
