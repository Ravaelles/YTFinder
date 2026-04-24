<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_toggle_favorite_status()
    {
        $video = Video::factory()->create(['is_favorite' => false]);

        // Test toggling ON
        $response1 = $this->putJson("/api/videos/{$video->id}/favorite")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'isFavorite' => true]);

        // Re-fetch to confirm database update (optional but good practice)
        $video->refresh();
        $this->assertTrue($video->is_favorite);

        // Test toggling OFF
        $response2 = $this->putJson("/api/videos/{$video->id}/favorite")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'isFavorite' => false]);

        $video->refresh();
        $this->assertFalse($video->is_favorite);
    }

    /** @test */
    public function it_can_track_a_click()
    {
        $video = Video::factory()->create(['click_count' => 10]);

        // Initial check
        $this->assertEquals(10, $video->fresh()->click_count);

        // Track click
        $response = $this->getJson("/api/videos/{$video->id}/track-click")
            ->assertStatus(200)
            ->assertJson(['message' => 'Clicked! Count updated.']);

        // Verify count incremented
        $video->refresh();
        $this->assertEquals(11, $video->click_count);
    }
}
