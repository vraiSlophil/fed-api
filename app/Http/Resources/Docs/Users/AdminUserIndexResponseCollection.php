<?php

namespace App\Http\Resources\Docs\Users;

use App\Http\Resources\Docs\PaginatedApiEnvelopeCollection;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Auth\User
 */
class AdminUserIndexResponseCollection extends PaginatedApiEnvelopeCollection
{
    public $collects = UserResource::class;

    /**
     * Return example metadata matching the admin users index contract.
     *
     * @return array<string, mixed>
     */
    protected function additionalMeta(Request $request): array
    {
        return [
            'sorting' => [
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'available_sort_fields' => [
                    'created_at',
                    'updated_at',
                    'username',
                    'email',
                    'first_name',
                    'last_name',
                    'last_login_at',
                    'email_verified_at',
                    'blocked_at',
                ],
            ],
            'filters' => [
                'search' => null,
                'theme_id' => null,
                'role' => null,
                'status' => null,
                'roles' => null,
            ],
            'roles' => [
                [
                    'power' => 100,
                    'name' => 'Admin',
                ],
                [
                    'power' => 10,
                    'name' => 'Member',
                ],
            ],
            'stats' => [
                'total_users' => 84,
                'active_users' => 82,
                'blocked_users' => 2,
                'verified_users' => 80,
                'unverified_users' => 4,
                'created_last_7_days' => 3,
                'verified_last_7_days' => 2,
                'blocked_last_7_days' => 1,
            ],
        ];
    }
}
