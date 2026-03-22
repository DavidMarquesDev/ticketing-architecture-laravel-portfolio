<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Eloquent;

final class TicketModel extends BaseEloquentModel
{
    protected $table = 'tickets';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'requester_id',
        'assignee_id',
        'status',
        'title',
        'description',
        'last_reply_at',
        'closed_at',
    ];
}
