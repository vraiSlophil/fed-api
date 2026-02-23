<?php

namespace App\Domain\Themes\Queries;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use Illuminate\Support\Collection;

class ThemeQueryService
{
    public function assertOwnedPlaygroundExists(User $user, string $playgroundId): void
    {
        Playground::query()
            ->where('playground_id', $playgroundId)
            ->where('user_id', $user->user_id)
            ->firstOrFail();
    }

    public function listForUser(User $user, ?string $playgroundId = null): Collection
    {
        $ownedThemes = Theme::query()
            ->where('owner_id', $user->user_id)
            ->when($playgroundId, fn ($query) => $query->where('playground_id', $playgroundId))
            ->get();

        $invitedThemes = Theme::query()
            ->whereHas('themeUserPermissions', function ($query) use ($user, $playgroundId): void {
                $query->where('user_id', $user->user_id)
                    ->where('can_view', true)
                    ->where('status', 'active')
                    ->when($playgroundId, fn ($q) => $q->where('target_playground_id', $playgroundId));
            })
            ->where('owner_id', '!=', $user->user_id)
            ->with(['themeUserPermissions' => fn ($query) => $query->where('user_id', $user->user_id)])
            ->get();

        $invitedThemes->each(function (Theme $theme): void {
            $permission = $theme->themeUserPermissions->first();
            $theme->permissions = $permission;
            $theme->target_playground_id = $permission?->target_playground_id;
            unset($theme->themeUserPermissions);
        });

        return $ownedThemes->concat($invitedThemes);
    }

    public function findViewableForUser(User $user, string $themeId): Theme
    {
        return Theme::query()
            ->where('theme_id', $themeId)
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->user_id)
                    ->orWhereHas('themeUserPermissions', function ($q) use ($user): void {
                        $q->where('user_id', $user->user_id)
                            ->where('can_view', true)
                            ->where('status', 'active');
                    });
            })
            ->firstOrFail();
    }
}
