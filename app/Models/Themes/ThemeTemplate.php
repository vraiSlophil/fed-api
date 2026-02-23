<?php

namespace App\Models\Themes;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThemeTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'template_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'visibility',
    ];

    protected $casts = [
        'visibility' => 'string',
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
     * Define the one-to-many relationship to theme template item using template_id and template_id keys.
     *
     * @return HasMany Configured relationship query definition.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ThemeTemplateItem::class, 'template_id', 'template_id')
            ->orderBy('position');
    }
}
