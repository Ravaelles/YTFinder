<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function favorite(Video $video)
    {
        $video->update([
            'is_favorite' => !$video->is_favorite,
        ]);

        return response()->json([
            'success' => true,
            'isFavorite' => $video->is_favorite,
        ]);
    }

    public function click(Video $video)
    {
        $video->increment('click_count');

        return response()->json([
            'message' => 'Clicked! Count updated.',
        ]);
    }
}
