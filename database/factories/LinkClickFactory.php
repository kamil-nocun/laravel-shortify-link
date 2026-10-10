<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\LinkClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkClick>
 */
class LinkClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => Link::factory(),
            'clicked_at' => now(),
            'country_code' => 'PL',
            'country' => 'Poland',
            'city' => 'Warsaw',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
        ];
    }

    /**
     * Indicate that the row is a daily summary of several clicks.
     */
    public function summary(int $clicks = 5): static
    {
        return $this->state(fn () => [
            'is_summary' => true,
            'city' => null,
            'clicks' => $clicks,
            'clicked_at' => now()->startOfDay(),
        ]);
    }
}
