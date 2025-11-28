<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playground extends Model
{
    use HasFactory, HasUuids;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class, 'playground_id', 'playground_id')
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Vérifie si ce playground est le playground par défaut de l'utilisateur
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Définit ce playground comme playground par défaut
     */
    public function setAsDefault(): void
    {
        // Retirer le statut par défaut des autres playgrounds de cet utilisateur
        static::where('user_id', $this->user_id)
            ->where('playground_id', '!=', $this->playground_id)
            ->update(['is_default' => false]);

        // Définir ce playground comme par défaut
        $this->update(['is_default' => true]);
    }
}
