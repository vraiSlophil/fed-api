<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondInvitationRequest extends FormRequest
{
    /**
     * Allow invitation response payload validation.
     *
     * @return bool Always true because invitation ownership is enforced in policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for accepting/declining an invitation.
     *
     * @return array Validation constraints keyed by request field name.
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['accepted', 'declined'])],
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }

    /**
     * Merge query-string and body payload for invitation response validation.
     *
     * @return array Validation payload combining query string and request body.
     */
    public function validationData(): array
    {
        return array_merge($this->query(), $this->all());
    }
}
