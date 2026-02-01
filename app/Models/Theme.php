<?php

namespace App\Models;

use App\Exceptions\ApiException;
use App\Invitations\Invitable;
use App\Models\Concerns\HasInvitations;
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
     * Vérifie si l'utilisateur donné est le propriétaire du thème
     */
    public function isOwnedBy(string $userId): bool
    {
        return $this->owner_id === $userId;
    }

    /**
     * Récupère les permissions d'un utilisateur spécifique pour ce thème
     */
    public function getPermissionsFor(string $userId): ?ThemeUserPermission
    {
        return $this->themeUserPermissions()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Vérifie si l'utilisateur a la permission de voir le thème
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
     * Vérifie si l'utilisateur a la permission de mettre à jour le thème
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
     * Vérifie si l'utilisateur a la permission d'ajouter une tâche
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
     * Vérifie si l'utilisateur a la permission de modifier une tâche
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
     * Vérifie si l'utilisateur a la permission de supprimer une tâche
     */
    public function canDeleteTaskBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canDeleteTask();
    }

    public function acceptInvitation(Invitation $invitation, ?string $targetPlaygroundId): ThemeUserPermission
    {
        if ($invitation->invitable_type !== self::class || $invitation->invitable_id !== $this->theme_id) {
            throw new ApiException('invitation.invalid', [], 400, 'Invalid invitation target');
        }

        if (ThemeUserPermission::where('theme_id', $this->theme_id)
            ->where('user_id', $invitation->invitee_user_id)
            ->exists()) {
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

        $permissions = $invitation->payload['permissions'] ?? [];

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
     * Vérifie si l'utilisateur a la permission de valider une tâche
     */
    public function canValidateTaskBy(string $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $permissions = $this->getPermissionsFor($userId);

        return $permissions && $permissions->canValidateTask();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    public function playground(): BelongsTo
    {
        return $this->belongsTo(Playground::class, 'playground_id', 'playground_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'theme_id', 'theme_id');
    }

    /**
     * Relation avec les permissions des utilisateurs pour ce thème
     */
    public function themeUserPermissions(): HasMany
    {
        return $this->hasMany(ThemeUserPermission::class, 'theme_id', 'theme_id');
    }
}
