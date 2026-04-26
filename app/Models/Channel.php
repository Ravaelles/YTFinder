<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
    use HasFactory;

    protected $fillable = [
        'handle',
        'display_name',
        'youtube_channel_id',
        'source_url',
        'last_scanned_at',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}
