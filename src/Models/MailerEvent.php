<?php

namespace Twenty20\Mailer\Models;

use Illuminate\Database\Eloquent\Model;

class MailerEvent extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mailer_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'provider',
        'email',
        'event_type',
        'reason',
        'message_id',
        'event_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'event_at' => 'datetime',
    ];
}
