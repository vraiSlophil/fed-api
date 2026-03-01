<?php

namespace App\Domain\Invitations\Queries;

use App\Models\Auth\User;
use App\Models\Invitations\Invitation;
use Illuminate\Pagination\LengthAwarePaginator;

class InvitationQueryService
{
    /**
     * Paginate invitations visible to the authenticated user.
     *
     * @param  User  $user  Current authenticated user used for authorization and ownership checks.
     * @param  ?string  $status  Requested status value applied by this method.
     * @param  string  $scope  Requested visibility scope (`inbox`, `outbox`, or `all`).
     * @param  array  $pagination  Pagination options such as page and per-page values.
     * @return LengthAwarePaginator Paginated collection of matching records.
     */
    public function paginateForUser(User $user, ?string $status, string $scope, array $pagination): LengthAwarePaginator
    {
        $query = Invitation::query()
            ->with([
                'inviter:user_id,username,email,first_name,last_name,avatar_path',
                'invitable',
            ]);

        if ($scope === 'outbox') {
            $query->where('inviter_user_id', $user->user_id);
        } elseif ($scope === 'all') {
            $query->where(function ($builder) use ($user): void {
                $builder->where('inviter_user_id', $user->user_id)
                    ->orWhere('invitee_user_id', $user->user_id);
            });
        } else {
            $query->where('invitee_user_id', $user->user_id);
        }

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('invitation_id')
            ->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);
    }
}
