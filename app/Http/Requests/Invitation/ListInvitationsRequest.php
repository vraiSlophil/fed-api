<?php

namespace App\Http\Requests\Invitation;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvitationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...OffsetPagination::queryRules(),
            'status' => ['sometimes', 'string', Rule::in(['pending', 'accepted', 'declined', 'expired'])],
        ];
    }
}
