<?php

namespace App\Http\Requests\Invitation;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvitationsRequest extends FormRequest
{
    /**
     * Allow authenticated users to validate invitation listing filters.
     *
     * @return bool Always true because access control is handled in controllers/policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for invitation status filtering and pagination.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return [
            ...OffsetPagination::queryRules(),
            'status' => ['sometimes', 'string', Rule::in(['pending', 'accepted', 'declined', 'expired', 'canceled'])],
            'scope' => ['sometimes', 'string', Rule::in(['inbox', 'outbox', 'all'])],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'page' => [
                'description' => 'Results page number.',
                'example' => 1,
            ],
            'per_page' => [
                'description' => 'Number of invitations per page.',
                'example' => 15,
            ],
            'status' => [
                'description' => 'Optional invitation status filter.',
                'example' => 'pending',
            ],
            'scope' => [
                'description' => 'Whether to list received invitations, sent invitations, or both.',
                'example' => 'inbox',
            ],
        ];
    }
}
