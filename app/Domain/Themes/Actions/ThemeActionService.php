<?php

namespace App\Domain\Themes\Actions;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Illuminate\Auth\Access\AuthorizationException;

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
     * @param  User  $actor  Current authenticated user used for authorization and ownership checks.
     * @param  Theme  $theme  Theme instance being read or mutated by this method.
     * @param  array  $validated  Validated payload extracted from the request.
     * @return Theme Theme instance returned after successful execution.
     */
    public function update(User $actor, Theme $theme, array $validated): Theme
    {
        if (array_key_exists('playground_id', $validated)) {
            if (! $theme->isOwnedBy($actor->user_id)) {
                throw new AuthorizationException('Only the owner can change theme playground');
            }

            Playground::query()
                ->where('playground_id', (string) $validated['playground_id'])
                ->where('user_id', $theme->owner_id)
                ->firstOrFail();
        }

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
