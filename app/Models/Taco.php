<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Taco extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slack_event_id',
        'giver_id',
        'receiver_id',
        'number_of_given_tacos',
        'message',
    ];

    public function slackEvent(): BelongsTo
    {
        return $this->belongsTo(SlackEvent::class);
    }
}
