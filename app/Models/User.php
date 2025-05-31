<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /* -----------------------------------------------------------------
     |  Configuration générale
     |-----------------------------------------------------------------*/
    protected $primaryKey = 'id'; // Clé primaire standard
    public $incrementing = true; // ID auto-incrémenté
    protected $keyType = 'int'; // Type de clé int

    // Attribut supplémentaire pour l'UUID
    protected $uuidKey = 'uuid';

    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar_path',
        'last_name',
        'first_name',
        'settings',
        'role_power',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'settings'      => 'array',
        'last_login_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  Boot : génération automatique d’un UUID v4 (binaire 16 o)
     |-----------------------------------------------------------------*/
    public static function create(array $array)
    {

        $user = new static($array);

        // On génère un UUID v4 binaire
        $user->{$user->getKeyName()} = self::uuidToBinary(Str::uuid()->toString());

        // On hash le mot de passe
        $user->setPasswordAttribute($array['password']);

        // On sauvegarde l’utilisateur
        $user->save();

        return $user;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user) {
            if (! $user->getKey()) {
                $user->{$user->getKeyName()} = self::uuidToBinary(Str::uuid()->toString());
            }
        });
    }

    /* -----------------------------------------------------------------
     |  Mutateurs & accesseurs
     |-----------------------------------------------------------------*/

    // Hashage automatique du mot de passe
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    // Convertit l’id binaire stocké ⇒ UUID lisible côté API
    public function getIdAttribute(string $value): string
    {
        return self::binaryToUuid($value);
    }

    /* -----------------------------------------------------------------
     |  Helpers UUID ⇄ binaire
     |-----------------------------------------------------------------*/
    public static function uuidToBinary(string $uuid): string
    {
        return pack('H*', str_replace('-', '', $uuid));
    }

    public static function binaryToUuid(string $binary): string
    {
        $hex = unpack('H*', $binary)[1];
        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split($hex, 4)
        );
    }
}
