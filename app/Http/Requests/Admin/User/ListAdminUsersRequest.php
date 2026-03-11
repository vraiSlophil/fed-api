<?php

namespace App\Http\Requests\Admin\User;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminUsersRequest extends FormRequest
{
    /**
     * Allow request validation; route authorization is enforced by admin policies.
     *
     * @return bool Always true because authorization is handled outside this request object.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for admin user listing filters, sorting, and pagination.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return [
            ...OffsetPagination::queryRules(),
            'search' => ['sometimes', 'string', 'min:3', 'max:255'],
            'theme_id' => ['sometimes', 'uuid', 'exists:themes,theme_id'],
            'role' => ['sometimes', 'integer', 'exists:roles,power'],
            'roles' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::in(['blocked', 'active', 'unverified'])],
            'sort_by' => ['sometimes', 'string', Rule::in([
                'created_at',
                'updated_at',
                'username',
                'email',
                'first_name',
                'last_name',
                'last_login_at',
                'email_verified_at',
                'blocked_at',
            ])],
            'sort' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'page' => ['description' => 'Results page number.', 'example' => 1],
            'per_page' => ['description' => 'Number of users per page.', 'example' => 15],
            'search' => ['description' => 'Free-text search applied to user attributes.', 'example' => 'john'],
            'theme_id' => ['description' => 'Theme context used for invitation search mode.', 'example' => '278fdd58-2050-4556-9393-8195d1a4ed74'],
            'role' => ['description' => 'Filter by role power.', 'example' => 100],
            'roles' => ['description' => 'Comma-separated list of role powers.', 'example' => '0,100'],
            'status' => ['description' => 'Filter by account state.', 'example' => 'active'],
            'sort_by' => ['description' => 'Sortable field name.', 'example' => 'created_at'],
            'sort' => ['description' => 'Sort direction.', 'example' => 'desc'],
        ];
    }
}
