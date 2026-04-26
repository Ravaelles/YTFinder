<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    /** @use HasFactory<\Database\Factories\VideoFactory> */
    use HasFactory;

    protected $fillable = [
        'youtube_video_id',
        'channel_id',
        'title',
        'duration_sec',
        'published_at',
        'view_count',
        'is_favorite',
        'click_count',
        'video_url',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_favorite' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
