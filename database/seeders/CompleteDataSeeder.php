<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Theme;
use App\Models\Task;
use App\Models\ThemeUserPermission;
use App\Models\UserMetric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CompleteDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Création d\'un jeu de données complet...');

        // 1. Créer les utilisateurs avec différents rôles
        $this->command->info('👥 Création des utilisateurs...');
        $users = $this->createUsers();

        // 2. Créer les métriques pour chaque utilisateur
        $this->command->info('📊 Création des métriques utilisateurs...');
        $this->createUserMetrics($users);

        // 3. Créer les thèmes
        $this->command->info('🎨 Création des thèmes...');
        $themes = $this->createThemes($users);

        // 4. Créer les permissions sur les thèmes
        $this->command->info('🔐 Création des permissions...');
        $this->createThemePermissions($themes, $users);

        // 5. Créer les tâches
        $this->command->info('✅ Création des tâches...');
        $this->createTasks($themes, $users);

        $this->command->info('✨ Jeu de données créé avec succès !');
        $this->displaySummary($users, $themes);
    }

    private function createUsers(): Collection
    {
        // Créer des super-administrateurs
        $superAdmins = User::factory()->count(2)->create([
            'role_power' => 1000,
        ]);

        // Créer des administrateurs
        $admins = User::factory()->count(5)->create([
            'role_power' => 100,
        ]);

        // Créer des utilisateurs normaux
        $users = User::factory()->count(43)->create([
            'role_power' => 10,
        ]);

        // Créer quelques utilisateurs bloqués
        $blockedUsers = User::factory()->count(5)->create([
            'role_power' => 10,
            'blocked_at' => now(),
        ]);

        return $superAdmins->merge($admins)->merge($users)->merge($blockedUsers);
    }

    private function createUserMetrics(Collection $users): void
    {
        $users->each(function (User $user) {
            // Générer les données de la factory selon le type d'utilisateur
            $factoryData = $this->getMetricsDataForUser($user);

            // Ajouter l'user_id et utiliser updateOrCreate pour éviter les conflits
            UserMetric::updateOrCreate(
                ['user_id' => $user->user_id],
                $factoryData
            );
        });
    }

    private function getMetricsDataForUser(User $user): array
    {
        // Les super-admins et admins sont plus actifs
        if ($user->role_power >= 100) {
            return UserMetric::factory()->veryActive()->make()->toArray();
        }
        // Utilisateurs bloqués ont peu d'activité
        elseif ($user->isBlocked()) {
            return UserMetric::factory()->newUser()->make()->toArray();
        }
        // Utilisateurs normaux avec activité variée
        else {
            $factory = fake()->boolean(30)
                ? UserMetric::factory()->veryActive()
                : UserMetric::factory();

            return $factory->make()->toArray();
        }
    }

    private function createThemes(Collection $users): Collection
    {
        $themes = collect();

        // Chaque utilisateur non bloqué crée entre 1 et 5 thèmes
        $users->where('blocked_at', null)->each(function (User $user) use ($themes) {
            $themeCount = fake()->numberBetween(1, 5);

            for ($i = 0; $i < $themeCount; $i++) {
                $theme = Theme::factory()->create([
                    'owner_id' => $user->user_id,
                ]);
                $themes->push($theme);
            }
        });

        return $themes;
    }

    private function createThemePermissions(Collection $themes, Collection $users): void
    {
        $activeUsers = $users->where('blocked_at', null);

        $themes->each(function (Theme $theme) use ($activeUsers) {
            // Le propriétaire n'a pas besoin de permissions explicites
            $otherUsers = $activeUsers->where('user_id', '!=', $theme->owner_id);

            if ($otherUsers->isEmpty()) {
                return;
            }

            // Chaque thème a entre 1 et 8 collaborateurs
            $collaboratorCount = fake()->numberBetween(1, min(8, $otherUsers->count()));
            $collaborators = $otherUsers->random($collaboratorCount);

            $collaborators->each(function (User $user) use ($theme) {
                // Éviter les doublons de permissions
                if (ThemeUserPermission::where('theme_id', $theme->theme_id)
                    ->where('user_id', $user->user_id)
                    ->exists()) {
                    return;
                }

                // Différents types de permissions selon le rôle
                if ($user->role_power >= 100) {
                    // Admins ont tous les droits
                    ThemeUserPermission::factory()->fullAccess()->create([
                        'theme_id' => $theme->theme_id,
                        'user_id' => $user->user_id,
                    ]);
                } else {
                    // Utilisateurs normaux avec permissions variées
                    $permissionType = fake()->randomElement(['full', 'limited', 'readonly', 'invited']);

                    $factory = match($permissionType) {
                        'full' => ThemeUserPermission::factory()->fullAccess(),
                        'readonly' => ThemeUserPermission::factory()->readOnly(),
                        'invited' => ThemeUserPermission::factory()->invited(),
                        default => ThemeUserPermission::factory()->active(),
                    };

                    $factory->create([
                        'theme_id' => $theme->theme_id,
                        'user_id' => $user->user_id,
                    ]);
                }
            });
        });
    }

    private function createTasks(Collection $themes, Collection $users): void
    {
        $themes->each(function (Theme $theme) use ($users) {
            // Récupérer les utilisateurs qui ont accès à ce thème
            $accessibleUsers = collect([$theme->owner_id]);

            // Ajouter les collaborateurs actifs
            $collaborators = ThemeUserPermission::where('theme_id', $theme->theme_id)
                ->where('status', 'active')
                ->pluck('user_id');

            $accessibleUsers = $accessibleUsers->merge($collaborators)->unique();

            if ($accessibleUsers->isEmpty()) {
                return;
            }

            // Chaque thème a entre 5 et 20 tâches
            $taskCount = fake()->numberBetween(5, 20);

            for ($i = 0; $i < $taskCount; $i++) {
                $assignedUser = $accessibleUsers->random();
                $status = fake()->randomElement(['todo', 'doing', 'done']);

                $taskData = [
                    'theme_id' => $theme->theme_id,
                    'user_id' => $assignedUser,
                    'status' => $status,
                ];

                // Si la tâche est terminée, ajouter une date de validation
                if ($status === 'done') {
                    $taskData['validated_at'] = fake()->dateTimeBetween('-30 days', 'now');
                }

                // Certaines tâches peuvent être archivées
                if (fake()->boolean(10)) {
                    $taskData['archived_at'] = fake()->dateTimeBetween('-15 days', 'now');
                }

                Task::factory()->create($taskData);
            }
        });
    }

    private function displaySummary(Collection $users, Collection $themes): void
    {
        $totalTasks = Task::count();
        $totalPermissions = ThemeUserPermission::count();

        $this->command->table(
            ['Entité', 'Nombre'],
            [
                ['Utilisateurs', $users->count()],
                ['- Super-admins', $users->where('role_power', 1000)->count()],
                ['- Admins', $users->where('role_power', 100)->count()],
                ['- Utilisateurs', $users->where('role_power', 10)->where('blocked_at', null)->count()],
                ['- Bloqués', $users->where('blocked_at', '!=', null)->count()],
                ['Thèmes', $themes->count()],
                ['Tâches', $totalTasks],
                ['Permissions', $totalPermissions],
            ]
        );
    }
}
