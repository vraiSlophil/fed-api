<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => (string) Str::uuid(),
            'theme_id' => null, // à renseigner lors de l'utilisation
            'user_id' => null,  // à renseigner lors de l'utilisation
            'title' => $this->faker->sentence(4),
            'status' => $this->faker->randomElement(['todo', 'doing', 'done']),
            'validated_at' => null,
            'archived_at' => null,
        ];
    }
}
