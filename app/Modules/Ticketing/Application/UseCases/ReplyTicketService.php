<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\ReplyTicketInputDTO;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Domain\Entities\TicketComment;
use App\Modules\Ticketing\Domain\Events\TicketReplied;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;

final class ReplyTicketService implements ReplyTicketUseCase
{
    public function __construct(
        private readonly TicketRepositoryPort $ticketRepository,
        private readonly TicketCommentRepositoryPort $ticketCommentRepository,
        private readonly TicketListCachePort $ticketListCache,
        private readonly DistributedLockPort $lock,
        private readonly EventDispatcherPort $eventDispatcher
    ) {
    }

    public function execute(ReplyTicketInputDTO $input): TicketComment
    {
        return $this->lock->execute(
            key: sprintf('ticket:reply:%s', $input->ticketId),
            seconds: 5,
            callback: function () use ($input): TicketComment {
                $ticket = $this->ticketRepository->findById($input->ticketId);

                if ($ticket === null) {
                    throw new TicketNotFoundException('Ticket não encontrado.');
                }

                $ticket->reply();
                $this->ticketRepository->save($ticket);

                $comment = TicketComment::create(
                    id: self::generateId(),
                    ticketId: $input->ticketId,
                    authorId: $input->authorId,
                    message: $input->message
                );

                $savedComment = $this->ticketCommentRepository->save($comment);

                $this->ticketListCache->forgetAll();
                $this->eventDispatcher->dispatch(
                    new TicketReplied(
                        ticketId: $input->ticketId,
                        commentId: $savedComment->id(),
                        authorId: $savedComment->authorId()
                    )
                );

                return $savedComment;
            }
        );
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
