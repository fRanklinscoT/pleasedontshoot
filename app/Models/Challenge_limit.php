<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class challenge_limit extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_count',
        'week_start'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);

    }
}
