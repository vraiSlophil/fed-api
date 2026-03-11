<?php

namespace App\Http\Resources\Docs\Users;

use App\Http\Resources\Docs\ApiEnvelopeResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Auth\User
 */
class AdminUserShowResponseResource extends ApiEnvelopeResource
{
    protected ?string $messageCode = 'user.show.success';

    protected function responseData(Request $request): mixed
    {
        return [
            'user' => UserResource::make($this->resource)->resolve($request),
            'additional_stats' => [
                'themes_count' => 4,
                'tasks_count' => 32,
                'completed_tasks_count' => 19,
                'completion_rate_percentage' => 59.4,
                'last_activity' => '2026-03-10T10:00:00+00:00',
                'account_age_days' => 42,
                'account_age_human' => '6 weeks ago',
                'days_since_last_login' => 2,
                'themes_as_member' => 2,
                'pending_invitations' => 1,
                'recent_activity' => [
                    'tasks_last_7_days' => 5,
                    'themes_last_7_days' => 1,
                    'active_days_last_30' => 12,
                ],
                'average_tasks_per_theme' => 8.0,
                'archived_tasks_count' => 3,
                'validated_tasks_count' => 14,
                'is_blocked' => false,
                'is_email_verified' => true,
                'blocked_since' => null,
                'verified_since' => '2 months ago',
            ],
        ];
    }
}
