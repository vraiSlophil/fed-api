<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Task extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'task_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'theme_id',
        'user_id',
        'title',
        'status',
        'validated_at',
        'archived_at',
    ];

    /**
     * Vérifie si une tâche est validée
     */
    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    /**
     * Valide une tâche
     */
    public function validate(): self
    {
        $this->status = 'done';
        $this->validated_at = now();
        return $this;
    }

    /**
     * Invalide une tâche (enlève la validation)
     */
    public function invalidate(): self
    {
        $this->validated_at = null;
        return $this;
    }

    /**
     * Gère automatiquement la mise à jour du statut
     */
    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = $value;

        // Si marquée comme terminée, ajouter la date de validation
        if ($value === 'done' && $this->validated_at === null) {
            $this->attributes['validated_at'] = now();
        }

        // Si on change le statut de done à autre chose, retirer la date de validation
        if ($value !== 'done' && $this->validated_at !== null) {
            $this->attributes['validated_at'] = null;
        }
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme_id', 'theme_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
