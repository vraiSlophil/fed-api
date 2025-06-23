<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ThemeUserPermission extends Model
{
    use HasFactory;

    protected $table = 'theme_user_permissions';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $fillable = [
        'theme_id',
        'user_id',
        'can_update_theme',
        'can_add_task',
        'can_delete_task',
        'can_validate_task',
        'status',
        'invited_at',
    ];

    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
