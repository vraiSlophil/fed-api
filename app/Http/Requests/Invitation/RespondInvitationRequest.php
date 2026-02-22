<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['accepted', 'declined'])],
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->query(), $this->all());
    }
}
