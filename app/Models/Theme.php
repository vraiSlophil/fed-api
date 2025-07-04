<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'theme_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'owner_id',
        'title',
        'color',
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

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    public function tasks()
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
