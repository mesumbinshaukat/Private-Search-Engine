<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'required|string|max:500',
            'category' => 'nullable|in:technology,business,ai,sports,politics,all',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'sort' => 'nullable|string|in:relevance,date_desc,date_asc',
            'debug' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Search query is required',
            'q.max' => 'Search query must not exceed 500 characters',
            'category.in' => 'Category must be one of: technology, business, ai, sports, politics, all',
            'page.min' => 'Page number must be at least 1',
            'per_page.min' => 'Per page value must be at least 1',
            'per_page.max' => 'Per page value must not exceed 100',
        ];
    }
}
