<?php

namespace Database\Factories;

use App\Models\Tasks\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tasks\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

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
            'status' => $this->faker->randomElement(['todo', 'in_progress', 'done']),
            'validated_at' => null,
            'archived_at' => null,
        ];
    }
}
