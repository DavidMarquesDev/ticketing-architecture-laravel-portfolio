<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Eloquent;

final class TicketCommentModel extends BaseEloquentModel
{
    /**
     * @var string
     */
    protected $table = 'ticket_comments';

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
        'ticket_id',
        'author_id',
        'message',
        'created_at',
        'updated_at',
    ];
}
