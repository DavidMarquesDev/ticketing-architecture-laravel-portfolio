<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PublishTicketAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $ticketId,
        public readonly int $requesterId
    ) {
        $this->onQueue('audits');
    }

    public function handle(): void
    {
        Log::info('ticket.created.audit', [
            'ticket_id' => $this->ticketId,
            'requester_id' => $this->requesterId,
        ]);
    }
}
