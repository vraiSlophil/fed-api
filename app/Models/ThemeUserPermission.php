<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ThemeUserPermission extends Model
{
    use HasFactory, HasUuids;

    /**
     * La clé primaire du modèle
     */
    protected $primaryKey = 'permission_id';

    /**
     * La clé primaire est composite (theme_id, user_id)
     */
    public $incrementing = false;

    /**
     * Le type de la clé primaire
     */
    protected $keyType = 'string';

    /**
     * Les attributs assignables en masse
     */
    protected $fillable = [
        'theme_id',
        'user_id',
        'can_view',
        'can_update_theme',
        'can_add_task',
        'can_edit_task',
        'can_delete_task',
        'can_validate_task',
        'status',
        'invited_at',
    ];

    /**
     * Les attributs qui doivent être convertis
     */
    protected $casts = [
        'can_view' => 'boolean',
        'can_update_theme' => 'boolean',
        'can_add_task' => 'boolean',
        'can_edit_task' => 'boolean',
        'can_delete_task' => 'boolean',
        'can_validate_task' => 'boolean',
        'invited_at' => 'datetime',
    ];

    /**
     * Vérifie si l'utilisateur est invité (pas encore actif)
     */
    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    /**
     * Vérifie si le statut est actif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Vérifie si le statut est inactif (révoqué)
     */
    public function isInactive(): bool
    {
        return $this->status === 'revoked';
    }

    /**
     * Vérifie si l'utilisateur peut voir le thème
     */
    public function canView(): bool
    {
        return $this->isActive() && $this->can_view;
    }

    /**
     * Vérifie si l'utilisateur peut mettre à jour le thème
     */
    public function canUpdateTheme(): bool
    {
        return $this->isActive() && $this->can_update_theme;
    }

    /**
     * Vérifie si l'utilisateur peut ajouter une tâche
     */
    public function canAddTask(): bool
    {
        return $this->isActive() && $this->can_add_task;
    }

    /**
     * Vérifie si l'utilisateur peut modifier une tâche
     */
    public function canEditTask(): bool
    {
        return $this->isActive() && $this->can_edit_task;
    }

    /**
     * Vérifie si l'utilisateur peut supprimer une tâche
     */
    public function canDeleteTask(): bool
    {
        return $this->isActive() && $this->can_delete_task;
    }

    /**
     * Vérifie si l'utilisateur peut valider une tâche
     */
    public function canValidateTask(): bool
    {
        return $this->isActive() && $this->can_validate_task;
    }

    /**
     * Relation avec le thème
     */
    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
