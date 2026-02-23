<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $primaryKey = 'power';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['power', 'name'];

    /**
     * Define the one-to-many relationship to user using role_power and power keys.
     *
     * @return HasMany Relationship used to access users assigned to this role power.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_power', 'power');
    }
}
