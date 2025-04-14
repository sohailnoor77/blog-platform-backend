<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blog>
 */
class BlogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $keywords = collect($this->faker->words(rand(3, 6)))
            ->map(fn($word) => Str::slug($word))
            ->toArray();

        $images = [
            'https://images.unsplash.com/photo-1503023345310-bd7c1de61c7d',
            'https://images.unsplash.com/photo-1508921912186-1d1a45ebb3c1',
            'https://images.unsplash.com/photo-1518779578993-ec3579fee39f',
            'https://images.unsplash.com/photo-1504198453319-5ce911bafcde',
            'https://images.unsplash.com/photo-1522202176988-66273c2fd55f',
        ];

        return [
            'user_id' => \App\Models\User::inRandomOrder()->first()?->id,
            'title' => Str::limit($this->faker->sentence, 255),
            'excerpt' => Str::limit($this->faker->sentence, 255),
            'description' => $this->faker->paragraphs(3, true),
            'image' => $images[array_rand($images)],
            'keywords' => $keywords,
            'meta_title' => Str::limit($this->faker->sentence, 255),
            'meta_description' => $this->faker->paragraph,
            'published_at' => now()->subDays(rand(0, 365)),
        ];
    }
}
