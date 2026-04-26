<?php

namespace Tests\Unit\Services;

use App\Services\YouTubeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeServiceTest extends TestCase
{
    public function test_it_parses_iso8601_duration()
    {
        $service = new YouTubeService();
        
        $this->assertEquals(0, $service->parseIso8601Duration('PT0S'));
        $this->assertEquals(10, $service->parseIso8601Duration('PT10S'));
        $this->assertEquals(60, $service->parseIso8601Duration('PT1M'));
        $this->assertEquals(65, $service->parseIso8601Duration('PT1M5S'));
        $this->assertEquals(3600, $service->parseIso8601Duration('PT1H'));
        $this->assertEquals(3661, $service->parseIso8601Duration('PT1H1M1S'));
    }

    public function test_it_parses_handle_from_url()
    {
        $service = new YouTubeService();
        
        $this->assertEquals('@username', $service->parseHandle('https://www.youtube.com/@username'));
        $this->assertEquals('@username', $service->parseHandle('https://www.youtube.com/@username/videos'));
    }

    public function test_it_fetches_channel_by_handle()
    {
        config(['services.youtube.key' => 'test-key']);
        
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UC123',
                        'snippet' => ['title' => 'Test Channel'],
                        'contentDetails' => [
                            'relatedPlaylists' => ['uploads' => 'UU123']
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new YouTubeService();
        $result = $service->fetchChannelByHandle('@test');

        $this->assertEquals('UC123', $result['channelId']);
        $this->assertEquals('Test Channel', $result['channelTitle']);
        $this->assertEquals('UU123', $result['uploadsPlaylistId']);
    }
}
