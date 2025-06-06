<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    /*  Mutateur : avant INSERT */
    public function setTokenableIdAttribute($value): void
    {
        // Si on reçoit un UUID lisible, on le compacte
        $this->attributes['tokenable_id'] =
            is_string($value) && strlen($value) === 36
                ? User::uuidToBinary($value)
                : $value;
    }

    /*  Accessor : après SELECT (facultatif, pratique pour les tests) */
    public function getTokenableIdAttribute($value): string
    {
        return User::binaryToUuid($value);
    }
}
