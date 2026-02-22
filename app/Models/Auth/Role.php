<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $primaryKey = 'power';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['power', 'name'];

    public function users()
    {
        return $this->hasMany(User::class, 'role_power', 'power');
    }
}
