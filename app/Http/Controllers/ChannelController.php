<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Video;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelController extends Controller
{
    public function __construct(protected YouTubeService $youtubeService)
    {
    }

    public function index()
    {
        return Channel::withCount('videos')->orderBy('created_at', 'desc')->get();
    }

    public function scan(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = $request->input('url');
        $handle = $this->youtubeService->parseHandle($url);

        $existing = Channel::where('handle', $handle)->first();
        if ($existing) {
            return response()->json($existing);
        }

        $scan = $this->youtubeService->scanChannel($url);

        return DB::transaction(function () use ($scan) {
            $channel = Channel::create([
                'handle' => $scan['handle'],
                'display_name' => $scan['displayName'],
                'youtube_channel_id' => $scan['youtubeChannelId'],
                'source_url' => $scan['sourceUrl'],
            ]);

            foreach ($scan['videos'] as $videoData) {
                $channel->videos()->create([
                    'youtube_video_id' => $videoData['youtubeVideoId'],
                    'title' => $videoData['title'],
                    'duration_sec' => $videoData['durationSec'],
                    'published_at' => $videoData['publishedAt'],
                    'view_count' => $videoData['viewCount'],
                    'video_url' => $videoData['videoUrl'],
                ]);
            }

            return response()->json($channel, 201);
        });
    }

    public function refresh(Channel $channel)
    {
        $scan = $this->youtubeService->scanChannel($channel->source_url);

        $existingIds = $channel->videos()->pluck('youtube_video_id')->toArray();
        $existingIdsSet = array_flip($existingIds);

        $newVideos = array_filter($scan['videos'], function ($v) use ($existingIdsSet) {
            return !isset($existingIdsSet[$v['youtubeVideoId']]);
        });

        DB::transaction(function () use ($channel, $scan, $newVideos) {
            foreach ($newVideos as $videoData) {
                $channel->videos()->create([
                    'youtube_video_id' => $videoData['youtubeVideoId'],
                    'title' => $videoData['title'],
                    'duration_sec' => $videoData['durationSec'],
                    'published_at' => $videoData['publishedAt'],
                    'view_count' => $videoData['viewCount'],
                    'video_url' => $videoData['videoUrl'],
                ]);
            }

            $channel->update([
                'display_name' => $scan['displayName'],
                'youtube_channel_id' => $scan['youtubeChannelId'],
                'last_scanned_at' => now(),
            ]);
        });

        return response()->json([
            'addedCount' => count($newVideos),
        ]);
    }

    public function videos(Channel $channel)
    {
        $videos = $channel->videos()->orderBy('published_at', 'desc')->get();
        
        return $videos->map(function ($v) {
            $v->durationLabel = $this->formatDuration($v->duration_sec);
            return $v;
        });
    }

    public function show(Channel $channel)
    {
        return [
            'greeting' => 'Yo! 👋 Peep this sick channel:',
            'channel' => $channel->load('videos'),
        ];
    }

    protected function formatDuration(int $durationSec): string
    {
        $h = floor($durationSec / 3600);
        $m = floor(($durationSec % 3600) / 60);
        $s = $durationSec % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%d:%02d', $m, $s);
    }
}
