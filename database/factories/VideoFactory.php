<?php

namespace Database\Factories;

use App\Models\Video;
use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'youtube_video_id' => $this->faker->unique()->regexify('[A-Za-z0-9_-]{11}'),
            'channel_id' => Channel::factory(),
            'title' => $this->faker->sentence(),
            'duration_sec' => $this->faker->numberBetween(60, 3600),
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'view_count' => $this->faker->numberBetween(100, 1000000),
            'is_favorite' => false,
            'click_count' => 0,
            'video_url' => 'https://www.youtube.com/watch?v=' . $this->faker->regexify('[A-Za-z0-9_-]{11}'),
        ];
    }

    public function forChannel(Channel $channel)
    {
        return $this->state(fn (array $attributes) => [
            'channel_id' => $channel->id,
        ]);
    }
}
