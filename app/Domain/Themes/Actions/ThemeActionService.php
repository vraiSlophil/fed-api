<?php

namespace App\Domain\Themes\Actions;

use App\Models\Auth\User;
use App\Models\Themes\Theme;

class ThemeActionService
{
    /**
     * Create a theme owned by the authenticated user in the target playground.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Theme Theme instance returned after successful execution.
     */
    public function create(User $user, array $validated): Theme
    {
        return Theme::create([
            'owner_id' => $user->user_id,
            'title' => $validated['title'],
            'color' => $validated['color'],
            'playground_id' => $validated['playground_id'],
        ]);
    }

    /**
     * Update editable theme fields and return the refreshed model.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Theme Theme instance returned after successful execution.
     */
    public function update(Theme $theme, array $validated): Theme
    {
        $theme->update($validated);

        return $theme->fresh();
    }

    /**
     * Permanently delete the provided theme.
     *
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @return void No return value.
     */
    public function delete(Theme $theme): void
    {
        $theme->delete();
    }
}
