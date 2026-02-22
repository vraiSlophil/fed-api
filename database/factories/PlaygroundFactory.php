<?php

namespace Database\Factories;

use App\Models\Playgrounds\Playground;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Playgrounds\Playground>
 */
class PlaygroundFactory extends Factory
{
    protected $model = Playground::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'playground_id' => (string) Str::uuid(),
            'user_id' => null, // à renseigner lors de l'utilisation
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'icon' => $this->faker->randomElement(['🏠', '💼', '🎯', '📚', '🎨', '⚡', '🌟', '🚀']),
            'color' => $this->faker->safeHexColor(),
            'background_color' => $this->faker->safeHexColor(),
            'style' => [],
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the playground is the default one.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
