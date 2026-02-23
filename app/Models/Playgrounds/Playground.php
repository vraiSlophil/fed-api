<?php

namespace App\Models\Playgrounds;

use App\Models\Auth\User;
use App\Models\Concerns\HasInvitations;
use App\Models\Themes\Theme;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playground extends Model
{
    use HasFactory, HasInvitations, HasUuids;

    protected $primaryKey = 'playground_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'color',
        'background_color',
        'style',
        'preview_image_url',
        'preview_updated_at',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'style' => 'array',
        'preview_updated_at' => 'datetime',
    ];

    /**
     * Define the belongs-to relationship to user using user_id and user_id keys.
     *
     * @return BelongsTo Configured relationship query definition.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Define the one-to-many relationship to theme using playground_id and playground_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class, 'playground_id', 'playground_id')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Determine whether this playground is the user's default playground.
     *
     * @return bool True when the condition is met; otherwise, false.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Mark this playground as default and unset default on sibling playgrounds.
     *
     * @return void No return value.
     */
    public function setAsDefault(): void
    {
        // Remove default status from the user's other playgrounds.
        static::where('user_id', $this->user_id)
            ->where('playground_id', '!=', $this->playground_id)
            ->update(['is_default' => false]);

        // Set this playground as default.
        $this->update(['is_default' => true]);
    }
}
