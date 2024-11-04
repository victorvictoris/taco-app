<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlackEvent extends Model
{
    use HasFactory;

    protected $fillable = ['slack_event_id'];

    public function tacos(): HasMany
    {
        return $this->hasMany(Taco::class);
    }
}
