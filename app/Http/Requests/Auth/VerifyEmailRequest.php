<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    /**
     * Allow signed email verification requests to be validated.
     *
     * @return bool Always true because signature and hash checks happen in the flow logic.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Provide query-string data used by verification validation rules.
     *
     * @return array Validation payload extracted from the request query string.
     */
    public function validationData(): array
    {
        return $this->query();
    }

    /**
     * Return validation rules for signed email verification query parameters.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid'],
            'hash' => ['required', 'string'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'id' => [
                'description' => 'User identifier embedded in the signed verification link.',
                'example' => '2a7188b7-8fd0-4bb9-9f9c-e61c3f4f7b24',
            ],
            'hash' => [
                'description' => 'Email verification hash from the signed verification link.',
                'example' => '6f8db599de986fab7a21625b7916589c',
            ],
            'expires' => [
                'description' => 'Signature expiration timestamp from the signed verification link.',
                'example' => '1773136800',
            ],
            'signature' => [
                'description' => 'Route signature validating the verification request.',
                'example' => 'generated-signature',
            ],
        ];
    }
}
