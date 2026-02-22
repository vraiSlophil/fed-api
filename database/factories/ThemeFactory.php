<?php

namespace Database\Factories;

use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Themes\Theme>
 */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

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
