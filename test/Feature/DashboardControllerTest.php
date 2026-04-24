<?php

namespace Tests\Feature;

use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_a_list_of_featured_channels()
    {
        // Setup: Create several channels with varying scan times
        $channel1 = Channel::factory()->create(['last_scanned_at' => Carbon::now()->subMinutes(5)]);
        $channel2 = Channel::factory()->create(['last_scanned_at' => Carbon::now()->subHours(1)]);
        $channel3 = Channel::factory()->create(['last_scanned_at' => Carbon::now()->subDays(2)]);

        // Ensure the order is correct (most recent first)
        $this->assertCount(3, Channel::all());

        // Act: Call the index endpoint
        $response = $this->getJson('/api/dashboard');

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        // Check greeting (casual check)
        $this->assertStringContainsString('Yo! 👋 Peep this sick channel:', $data['greeting']); // Note: This assumes the ChannelController logic is being tested here, but based on DashboardController::index(), we test its specific greeting.
        $this->assertStringContainsString("What's up! 😎 Here are some fresh finds:", $data['greeting']);


        // Check featured channels structure and count (should be 3 in this setup)
        $featured = $data['featuredChannels'];
        $this->assertIsArray($featured);
        $this->assertCount(3, $featured);

        // Check ordering: Channel 1 (5 mins ago) should come before Channel 2 (1 hour ago)
        $this->assertEquals($channel1->id, $featured[0]['id']);
        $this->assertEquals($channel2->id, $featured[1]['id']);
        $this->assertEquals($channel3->id, $featured[2]['id']);

        // Check data integrity for the first item
        $this->assertEquals('Awesome Channel 1', $featured[0]['displayName']);
    }
}
