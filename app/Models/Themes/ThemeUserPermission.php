<?php

namespace App\Models\Themes;

use App\Models\Auth\User;
use App\Models\Playgrounds\Playground;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeUserPermission extends Model
{
    use HasFactory, HasUuids;

    /**
     * The model primary key.
     */
    protected $primaryKey = 'permission_id';

    /**
     * The model does not use an auto-incrementing key.
     */
    public $incrementing = false;

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * The mass assignable attributes.
     */
    protected $fillable = [
        'theme_id',
        'user_id',
        'target_playground_id',
        'can_view',
        'can_update_theme',
        'can_add_task',
        'can_edit_task',
        'can_delete_task',
        'can_validate_task',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'can_view' => 'boolean',
        'can_update_theme' => 'boolean',
        'can_add_task' => 'boolean',
        'can_edit_task' => 'boolean',
        'can_delete_task' => 'boolean',
        'can_validate_task' => 'boolean',
    ];

    /**
     * Determine whether the permission entry is active.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the permission entry is revoked.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isInactive(): bool
    {
        return $this->status === 'revoked';
    }

    /**
     * Determine whether the user can view the theme.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canView(): bool
    {
        return $this->isActive() && $this->can_view;
    }

    /**
     * Determine whether the user can update the theme.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canUpdateTheme(): bool
    {
        return $this->isActive() && $this->can_update_theme;
    }

    /**
     * Determine whether the user can add tasks in the theme.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canAddTask(): bool
    {
        return $this->isActive() && $this->can_add_task;
    }

    /**
     * Determine whether the user can edit tasks in the theme.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canEditTask(): bool
    {
        return $this->isActive() && $this->can_edit_task;
    }

    /**
     * Determine whether the user can delete tasks in the theme.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canDeleteTask(): bool
    {
        return $this->isActive() && $this->can_delete_task;
    }

    /**
     * Determine whether the user can validate tasks in the theme.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function canValidateTask(): bool
    {
        return $this->isActive() && $this->can_validate_task;
    }

    /**
     * Define the belongs-to relationship to playground using target_playground_id and playground_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function targetPlayground(): BelongsTo
    {
        return $this->belongsTo(Playground::class, 'target_playground_id', 'playground_id');
    }

    /**
     * Define the belongs-to relationship to theme using theme_id and theme_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

    /**
     * Define the belongs-to relationship to user using user_id and user_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
