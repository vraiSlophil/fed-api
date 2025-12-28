<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ThemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'theme_id' => (string) Str::uuid(),
            'owner_id' => null, // à renseigner lors de l'utilisation
            'playground_id' => null, // à renseigner lors de l'utilisation
            'title' => $this->faker->sentence(3),
            'color' => $this->faker->safeHexColor(),
        ];
    }
}
