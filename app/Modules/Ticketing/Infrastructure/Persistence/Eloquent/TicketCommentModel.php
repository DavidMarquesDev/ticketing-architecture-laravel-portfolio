<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Eloquent;

final class TicketCommentModel extends BaseEloquentModel
{
    protected $table = 'ticket_comments';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ticket_id',
        'author_id',
        'message',
        'created_at',
        'updated_at',
    ];
}
