<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondInvitationRequest extends FormRequest
{
    /**
     * Allow authenticated invitation response payload validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for authenticated invitation responses.
     *
     * @return array Validation constraints keyed by request field name.
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['accepted', 'declined', 'canceled'])],
            'target_playground_id' => ['nullable', 'uuid', 'exists:playgrounds,playground_id', 'prohibited_unless:status,accepted'],
        ];
    }

    /**
     * Restrict validation to request-body data so query parameters cannot drive invitation mutations.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        if ($this->isJson()) {
            $payload = $this->json()->all();

            return is_array($payload) ? $payload : [];
        }

        return $this->request->all();
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Invitation response status. Invitees may send `accepted` or `declined`. Inviters and admins may send `canceled`.',
                'example' => 'accepted',
            ],
            'target_playground_id' => [
                'description' => 'Optional destination playground UUID used when accepting into a non-default playground. Omit it to use the invitee default playground when available.',
                'example' => '5e4f4aa4-a102-4878-8b86-9623a02f2f01',
            ],
        ];
    }
}
