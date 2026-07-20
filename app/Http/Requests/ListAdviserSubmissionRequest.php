<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAdviserSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Only NEP Coordinator and Admin can retrieve submission records
        return $user && in_array($user->role, ['nep_admin', 'nep_coordinator']);
    }

    public function rules(): array
    {
        return [
            'analysis_scope' => ['nullable', 'string', 'in:full map,geographic subset,thematic subset'],
            'status' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'analysis_scope.in' => 'The analysis scope must be one of: full map, geographic subset, thematic subset.',
            'per_page.min' => 'The per_page parameter must be at least 1.',
            'per_page.max' => 'The per_page parameter may not exceed 100.',
        ];
    }
}