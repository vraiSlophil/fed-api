<?php

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use App\Models\Themes\Theme;
use App\Models\Themes\ThemeUserPermission;
use Illuminate\Database\QueryException;

it('enforces database check constraint on theme permission invariants', function () {
    $owner = User::factory()->create();
    $ownerPlayground = Playground::query()
        ->where('user_id', $owner->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    $theme = Theme::factory()->create([
        'owner_id' => $owner->user_id,
        'playground_id' => $ownerPlayground->playground_id,
    ]);

    $member = User::factory()->create();
    $memberDefaultPlayground = Playground::query()
        ->where('user_id', $member->user_id)
        ->where('is_default', true)
        ->firstOrFail();

    expect(function () use ($theme, $member, $memberDefaultPlayground): void {
        ThemeUserPermission::query()->create([
            'theme_id' => $theme->theme_id,
            'user_id' => $member->user_id,
            'target_playground_id' => $memberDefaultPlayground->playground_id,
            'can_view' => false,
            'can_update_theme' => false,
            'can_add_task' => false,
            'can_edit_task' => true,
            'can_delete_task' => false,
            'can_validate_task' => false,
            'status' => 'active',
        ]);
    })->toThrow(QueryException::class);
});
