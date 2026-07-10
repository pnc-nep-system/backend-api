<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via canManage()
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
            'activities.*.inclusion_group' => ['nullable', 'string'],
            'activities.*.inclusion_type' => ['nullable', 'string'],
            'activities.*.source' => ['sometimes', Rule::in(['ai_confirmed', 'ai_modified', 'human_entered'])],
            'activities.*.education_level_ids' => ['required', 'array', 'min:1'],
            'activities.*.education_level_ids.*' => ['integer', 'exists:education_levels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'activities.*.activity_item_id.exists' => 'The selected activity is invalid, inactive, or deprecated.',
        ];
    }
}