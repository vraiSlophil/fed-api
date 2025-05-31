<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public static function boot()
    {
        parent::boot();

        // Intercepter le processus de création pour convertir l'UUID en binaire
        static::creating(function ($model) {
            if (isset($model->tokenable_id) && is_string($model->tokenable_id) && strlen($model->tokenable_id) === 36) {
                // Convertir l'UUID en binaire avant l'enregistrement
                $model->attributes['tokenable_id'] = User::uuidToBinary($model->tokenable_id);
            }
        });
    }
}
