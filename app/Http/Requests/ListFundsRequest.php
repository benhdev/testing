<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFundsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', Rule::in(['growth', 'responsible'])],
            'risk' => ['sometimes', Rule::in(['cautious', 'balanced', 'adventurous', 'responsible'])],
            'min_analyst_rating' => ['sometimes', 'integer', 'between:1,5'],
            'max_ongoing_charge' => ['sometimes', 'numeric', 'min:0'],
            'sort' => ['sometimes', Rule::in([
                'name', '-name',
                'ongoing_charge', '-ongoing_charge',
                'analyst_rating', '-analyst_rating',
            ])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
