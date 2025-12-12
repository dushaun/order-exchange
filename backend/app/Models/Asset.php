<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = [
        'user_id',
        'symbol',
        'amount',
        'locked_amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
