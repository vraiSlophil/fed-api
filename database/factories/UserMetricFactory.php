<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserMetric>
 */
class UserMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalThemes = $this->faker->numberBetween(0, 20);
        $totalTasks = $this->faker->numberBetween(0, 100);
        $completedTasks = $this->faker->numberBetween(0, $totalTasks);
        $currentStreak = $this->faker->numberBetween(0, 30);
        $longestStreak = $this->faker->numberBetween($currentStreak, 60);

        return [
            // Ne pas définir user_id ici, il sera fourni lors de l'appel create()
            'total_themes_created' => $totalThemes,
            'total_tasks_created' => $totalTasks,
            'total_tasks_completed' => $completedTasks,
            'current_streak_days' => $currentStreak,
            'longest_streak_days' => $longestStreak,
            'last_activity_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'themes_created_this_week' => $this->faker->numberBetween(0, 5),
            'themes_created_last_week' => $this->faker->numberBetween(0, 5),
            'tasks_created_this_week' => $this->faker->numberBetween(0, 20),
            'tasks_created_last_week' => $this->faker->numberBetween(0, 20),
            'tasks_completed_this_week' => $this->faker->numberBetween(0, 15),
            'tasks_completed_last_week' => $this->faker->numberBetween(0, 15),
        ];
    }

    /**
     * État pour un utilisateur très actif
     */
    public function veryActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_themes_created' => $this->faker->numberBetween(15, 50),
            'total_tasks_created' => $this->faker->numberBetween(80, 200),
            'total_tasks_completed' => $this->faker->numberBetween(60, 180),
            'current_streak_days' => $this->faker->numberBetween(7, 45),
            'longest_streak_days' => $this->faker->numberBetween(20, 90),
            'themes_created_this_week' => $this->faker->numberBetween(2, 8),
            'tasks_created_this_week' => $this->faker->numberBetween(10, 30),
            'tasks_completed_this_week' => $this->faker->numberBetween(8, 25),
        ]);
    }

    /**
     * État pour un nouvel utilisateur
     */
    public function newUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_themes_created' => $this->faker->numberBetween(0, 3),
            'total_tasks_created' => $this->faker->numberBetween(0, 10),
            'total_tasks_completed' => $this->faker->numberBetween(0, 5),
            'current_streak_days' => $this->faker->numberBetween(0, 5),
            'longest_streak_days' => $this->faker->numberBetween(0, 7),
            'themes_created_this_week' => $this->faker->numberBetween(0, 2),
            'tasks_created_this_week' => $this->faker->numberBetween(0, 5),
            'tasks_completed_this_week' => $this->faker->numberBetween(0, 3),
        ]);
    }
}
