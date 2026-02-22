<?php

namespace App\Domain\Themes\Actions;

use App\Models\Auth\User;
use App\Models\Themes\Theme;

class ThemeActionService
{
    public function create(User $user, array $validated): Theme
    {
        return Theme::create([
            'owner_id' => $user->user_id,
            'title' => $validated['title'],
            'color' => $validated['color'],
            'playground_id' => $validated['playground_id'],
        ]);
    }

    public function update(Theme $theme, array $validated): Theme
    {
        $theme->update($validated);

        return $theme->fresh();
    }

    public function delete(Theme $theme): void
    {
        $theme->delete();
    }
}
