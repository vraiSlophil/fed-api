<?php

namespace Database\Factories;

use App\Models\Invitations\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'invitation_id' => (string) Str::uuid(),
            'inviter_user_id' => null,
            'invitee_user_id' => null,
            'invitable_type' => null,
            'invitable_id' => null,
            'payload' => null,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ];
    }
}
