<?php

namespace App\Http\Requests\Playground;

use App\Support\Pagination\OffsetPagination;
use Illuminate\Foundation\Http\FormRequest;

class ListPlaygroundThemesRequest extends FormRequest
{
    /**
     * Allow validation of playground-theme listing parameters.
     *
     * @return bool Always true because playground visibility is enforced later in the flow.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return pagination rules for listing themes inside a playground.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return OffsetPagination::queryRules();
    }
}
