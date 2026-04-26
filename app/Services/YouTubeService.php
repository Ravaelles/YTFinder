<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class YouTubeService
{
    protected ?string $apiKey;
    protected string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.key');
    }

    public function parseIso8601Duration(string $duration): int
    {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        
        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    public function parseHandle(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            throw new Exception("Invalid URL");
        }
        
        $segments = array_filter(explode('/', $path));
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '@')) {
                return $segment;
            }
        }

        throw new Exception("Please provide a channel URL with @handle");
    }

    protected function ytFetch(string $path, array $params): array
    {
        $response = Http::get("{$this->baseUrl}/{$path}", array_merge($params, [
            'key' => $this->apiKey,
        ]));

        if ($response->failed()) {
            throw new Exception("YouTube API error on {$path}: " . $response->status() . " " . $response->body());
        }

        return $response->json();
    }

    public function fetchChannelByHandle(string $handle): array
    {
        $payload = $this->ytFetch('channels', [
            'part' => 'snippet,contentDetails',
            'forHandle' => ltrim($handle, '@'),
            'maxResults' => '1',
        ]);

        if (empty($payload['items'])) {
            throw new Exception("No channel found for handle {$handle}");
        }

        $channel = $payload['items'][0];
        $uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        
        if (!$uploadsPlaylistId) {
            throw new Exception("Uploads playlist not available for handle {$handle}");
        }

        return [
            'channelId' => $channel['id'],
            'channelTitle' => $channel['snippet']['title'],
            'uploadsPlaylistId' => $uploadsPlaylistId,
        ];
    }

    public function fetchAllVideosByUploadsPlaylist(string $uploadsPlaylistId): array
    {
        $videoIds = [];
        $pageToken = null;

        do {
            $payload = $this->ytFetch('playlistItems', [
                'part' => 'snippet',
                'playlistId' => $uploadsPlaylistId,
                'maxResults' => '50',
                'pageToken' => $pageToken ?? '',
            ]);

            foreach ($payload['items'] as $item) {
                if (isset($item['snippet']['resourceId']['videoId'])) {
                    $videoIds[] = $item['snippet']['resourceId']['videoId'];
                }
            }

            $pageToken = $payload['nextPageToken'] ?? null;
        } while ($pageToken);

        return $videoIds;
    }

    public function fetchVideoDetails(array $videoIds): array
    {
        $chunks = array_chunk($videoIds, 50);
        $detailed = [];

        foreach ($chunks as $chunk) {
            $payload = $this->ytFetch('videos', [
                'part' => 'contentDetails,statistics,snippet',
                'id' => implode(',', $chunk),
            ]);
            $detailed = array_merge($detailed, $payload['items']);
        }

        return $detailed;
    }

    public function scanChannel(string $url): array
    {
        $handle = $this->parseHandle($url);
        $channelData = $this->fetchChannelByHandle($handle);
        $videoIds = $this->fetchAllVideosByUploadsPlaylist($channelData['uploadsPlaylistId']);

        if (empty($videoIds)) {
            throw new Exception("No videos found for handle {$handle}");
        }

        $details = $this->fetchVideoDetails($videoIds);
        $detailMap = collect($details)->keyBy('id');

        $videos = array_map(function ($id) use ($detailMap) {
            $detail = $detailMap->get($id);
            if (!$detail) return null;

            return [
                'youtubeVideoId' => $id,
                'title' => $detail['snippet']['title'],
                'durationSec' => $this->parseIso8601Duration($detail['contentDetails']['duration']),
                'publishedAt' => Carbon::parse($detail['snippet']['publishedAt']),
                'viewCount' => (int) ($detail['statistics']['viewCount'] ?? 0),
                'videoUrl' => "https://www.youtube.com/watch?v={$id}",
            ];
        }, $videoIds);

        return [
            'handle' => $handle,
            'sourceUrl' => $url,
            'displayName' => $channelData['channelTitle'],
            'youtubeChannelId' => $channelData['channelId'],
            'videos' => array_filter($videos),
        ];
    }
}
