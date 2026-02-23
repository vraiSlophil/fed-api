<?php

namespace App\Models\Concerns;

use App\Models\Invitations\Invitation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInvitations
{
    /**
     * Define the polymorphic one-to-many relationship to invitation using invitable as the foreign key.
     *
     * @return MorphMany Configured relationship query definition.
     */
    public function invitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invitable');
    }
}
