<?php

namespace Database\Factories;

use App\Models\Themes\ThemeUserPermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Themes\ThemeUserPermission>
 */
class ThemeUserPermissionFactory extends Factory
{
    protected $model = ThemeUserPermission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permission_id' => (string) Str::uuid(),
            'theme_id' => null, // à renseigner lors de l'utilisation
            'user_id' => null,  // à renseigner lors de l'utilisation
            'can_view' => true,
            'can_update_theme' => $this->faker->boolean(30), // 30% de chance
            'can_add_task' => $this->faker->boolean(70), // 70% de chance
            'can_edit_task' => $this->faker->boolean(60), // 60% de chance
            'can_delete_task' => $this->faker->boolean(40), // 40% de chance
            'can_validate_task' => $this->faker->boolean(50), // 50% de chance
            'status' => $this->faker->randomElement(['active', 'revoked']),
        ];
    }

    /**
     * État pour des permissions d'administrateur complet
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_view' => true,
            'can_update_theme' => true,
            'can_add_task' => true,
            'can_edit_task' => true,
            'can_delete_task' => true,
            'can_validate_task' => true,
            'status' => 'active',
        ]);
    }

    /**
     * État pour des permissions de lecture seule
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_view' => true,
            'can_update_theme' => false,
            'can_add_task' => false,
            'can_edit_task' => false,
            'can_delete_task' => false,
            'can_validate_task' => false,
            'status' => 'active',
        ]);
    }

    /**
     * État pour un utilisateur actif
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
