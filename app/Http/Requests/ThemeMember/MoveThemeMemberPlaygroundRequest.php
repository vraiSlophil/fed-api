<?php

namespace App\Http\Requests\ThemeMember;

use Illuminate\Foundation\Http\FormRequest;

class MoveThemeMemberPlaygroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_playground_id' => ['required', 'uuid', 'exists:playgrounds,playground_id'],
        ];
    }
}
