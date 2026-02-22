<?php

namespace App\Http\Requests\Admin\User;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...OffsetPagination::queryRules(),
            'search' => ['sometimes', 'string', 'max:255'],
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
}
