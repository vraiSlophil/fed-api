<?php

namespace App\Models\Concerns;

use App\Models\Invitation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInvitations
{
    public function invitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invitable');
    }
}
