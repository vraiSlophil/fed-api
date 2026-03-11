<?php

namespace App\Http\Requests\Metrics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserMetricsRequest extends FormRequest
{
    /**
     * Allow authenticated users to request their analytics snapshots.
     *
     * @return bool Always true because user scoping is applied in the controller/service.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for supported analytics period keys.
     *
     * @return array Validation constraints keyed by query parameter name.
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['7_days', '30_days', '3_months', '6_months', '12_months', 'all_time'])],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'period' => [
                'description' => 'Reporting window used for user analytics.',
                'example' => '12_months',
            ],
        ];
    }
}
