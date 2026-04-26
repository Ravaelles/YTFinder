<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'handle' => '@' . $this->faker->unique()->userName(),
            'display_name' => $this->faker->name(),
            'youtube_channel_id' => 'UC' . $this->faker->unique()->regexify('[A-Za-z0-9_-]{22}'),
            'source_url' => 'https://www.youtube.com/@' . $this->faker->userName(),
            'last_scanned_at' => now(),
        ];
    }
}
