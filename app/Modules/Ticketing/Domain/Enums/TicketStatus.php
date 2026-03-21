<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Domain\Enums;

/**
 * Representa os estados válidos do ticket.
 *
 * @author David Marques
 */
enum TicketStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case CLOSED = 'closed';
}
