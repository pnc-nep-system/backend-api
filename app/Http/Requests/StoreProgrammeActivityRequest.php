<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.activity_item_id' => [
                'required',
                'integer',
                Rule::exists('taxonomy_items', 'id')->where('is_active', true),
            ],
            'activities.*.is_primary' => ['sometimes', 'boolean'],
            'activities.*.inclusion_group' => ['nullable', 'string', 'max:255'],
            'activities.*.inclusion_type' => ['nullable', 'string', 'max:255'],
            'activities.*.source' => ['sometimes', Rule::in(['ai_confirmed', 'ai_modified', 'human_entered'])],
            'activities.*.education_level_ids' => ['required', 'array', 'min:1'],
            'activities.*.education_level_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('education_levels', 'id'),
            ],
        ];
    }
}
