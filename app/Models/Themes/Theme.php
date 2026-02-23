<?php

namespace App\Models\Themes;

use App\Exceptions\ApiException;
use App\Invitations\Invitable;
use App\Models\Auth\User;
use App\Models\Concerns\HasInvitations;
use App\Models\Invitations\Invitation;
use App\Models\Playgrounds\Playground;
use App\Models\Tasks\Task;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model implements Invitable
{
    use HasFactory, HasInvitations, HasUuids;

    protected $primaryKey = 'theme_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_id',
        'playground_id',
        'title',
        'color',
        'visibility',
    ];

    protected $casts = [
        'visibility' => 'string',
    ];

    /**
     * Determine whether the theme is owned by the specified user.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isOwnedBy(string $userId): bool
    {
        return $this->owner_id === $userId;
    }

    /**
     * Return theme permissions for the specified user.
     *
     * @param  string  $userId  Identifier of the user.
     * @return ?ThemeUserPermission ThemeUserPermission instance returned after successful execution.
     */
    public function getPermissionsFor(string $userId): ?ThemeUserPermission
    {
        return $this->themeUserPermissions()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Determine whether the theme can be viewed by the specified user.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canBeViewedBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canView();
    }

    /**
     * Determine whether the theme can be updated by the specified user.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canBeUpdatedBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canUpdateTheme();
    }

    /**
     * Determine whether the specified user can add tasks to the theme.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canAddTaskBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canAddTask();
    }

    /**
     * Determine whether the specified user can edit tasks in the theme.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canEditTaskBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canEditTask();
    }

    /**
     * Determine whether the specified user can delete tasks in the theme.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canDeleteTaskBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canDeleteTask();
    }

    /**
     * Grant theme permissions when an invitation is accepted.
     *
     * @param  Invitation  $invitation  Invitation instance being processed by this method.
     * @param  ?string  $targetPlaygroundId  Identifier of the destination playground for shared access.
     * @return ThemeUserPermission ThemeUserPermission instance returned after successful execution.
     *
     * @throws \App\Exceptions\ApiException When the operation cannot be completed.
     */
    public function acceptInvitation(Invitation $invitation, ?string $targetPlaygroundId): ThemeUserPermission
    {
        if ($invitation->invitable_type !== self::class || $invitation->invitable_id !== $this->theme_id) {
            throw new ApiException('invitation.invalid', [], 400, 'Invalid invitation target');
        }

        if (
            ThemeUserPermission::where('theme_id', $this->theme_id)
                ->where('user_id', $invitation->invitee_user_id)
                ->exists()
        ) {
            throw new ApiException('theme.member.already_exists', ['user_id' => $invitation->invitee_user_id], 409, 'User is already a member of this theme');
        }

        if ($targetPlaygroundId) {
            Playground::where('playground_id', $targetPlaygroundId)
                ->where('user_id', $invitation->invitee_user_id)
                ->firstOrFail();
        } else {
            $defaultPlayground = Playground::where('user_id', $invitation->invitee_user_id)
                ->where('is_default', true)
                ->first();

            $targetPlaygroundId = $defaultPlayground?->playground_id;
        }

        $payload = $invitation->payload;
        $permissions = is_array($payload) ? ($payload['permissions'] ?? []) : [];

        return ThemeUserPermission::create([
            'theme_id' => $this->theme_id,
            'user_id' => $invitation->invitee_user_id,
            'can_view' => (bool) ($permissions['can_view'] ?? false),
            'can_update_theme' => (bool) ($permissions['can_update_theme'] ?? false),
            'can_add_task' => (bool) ($permissions['can_add_task'] ?? false),
            'can_edit_task' => (bool) ($permissions['can_edit_task'] ?? false),
            'can_delete_task' => (bool) ($permissions['can_delete_task'] ?? false),
            'can_validate_task' => (bool) ($permissions['can_validate_task'] ?? false),
            'status' => 'active',
            'target_playground_id' => $targetPlaygroundId,
        ]);
    }

    /**
     * Determine whether the specified user can validate tasks in the theme.
     *
     * @param  string  $userId  Identifier of the user.
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canValidateTaskBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canValidateTask();
    }

    /**
     * Define the belongs-to relationship to user using owner_id and user_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    /**
     * Define the belongs-to relationship to playground using playground_id and playground_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function playground(): BelongsTo
    {
        return $this->belongsTo(Playground::class, 'playground_id', 'playground_id');
    }

    /**
     * Define the one-to-many relationship to task using theme_id and theme_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'theme_id', 'theme_id');
    }

    /**
     * Define the one-to-many relationship to theme user permission using theme_id and theme_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function themeUserPermissions(): HasMany
    {
        return $this->hasMany(ThemeUserPermission::class, 'theme_id', 'theme_id');
    }
}
