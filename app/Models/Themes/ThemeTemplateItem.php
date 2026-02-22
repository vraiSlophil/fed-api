<?php

namespace App\Models\Themes;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeTemplateItem extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'template_id',
        'position',
        'title',
        'default_description',
        'default_status',
        'default_metadata',
    ];

    protected $casts = [
        'default_metadata' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ThemeTemplate::class, 'template_id', 'template_id');
    }
}
