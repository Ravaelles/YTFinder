<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ChannelControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_shows_channel_details_with_casual_greeting()
    {
        // Setup: Create a channel and associate some videos
        $channel = Channel::factory()->create(['handle' => 'testuser']);
        Video::factory(3)->forChannel($channel);

        // Act: Hit the show endpoint
        $response = $this->getJson("/api/channels/{$channel->id}");

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        // Test casual greeting logic implemented in ChannelController::show()
        $this->assertEquals("Yo! 👋 Peep this sick channel:", $data['greeting']);

        // Test channel data and associated videos count
        $this->assertEquals($channel->id, $data['channel']['id']);
        $this->assertCount(3, $data['channel']['videos']);
    }
}
