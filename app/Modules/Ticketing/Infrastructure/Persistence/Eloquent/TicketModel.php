<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Eloquent;

final class TicketModel extends BaseEloquentModel
{
    /**
     * @var string
     */
    protected $table = 'tickets';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
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
