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
}
