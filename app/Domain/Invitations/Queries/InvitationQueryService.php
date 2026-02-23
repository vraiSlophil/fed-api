<?php

namespace App\Domain\Invitations\Queries;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use Illuminate\Pagination\LengthAwarePaginator;

class InvitationQueryService
{
    /**
     * Paginate invitations received by the authenticated invitee.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  string  $status  Requested status value applied by this method.
     * @param  array  $pagination  Pagination options such as page and per-page values.
     * @return LengthAwarePaginator Paginated collection of matching records.
     */
    public function paginateForInvitee(User $user, string $status, array $pagination): LengthAwarePaginator
    {
        return Invitation::query()
            ->where('invitee_user_id', $user->user_id)
            ->where('status', $status)
            ->with([
                'inviter:user_id,username,email,first_name,last_name,avatar_path',
                'invitable',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('invitation_id')
            ->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
    }
}
