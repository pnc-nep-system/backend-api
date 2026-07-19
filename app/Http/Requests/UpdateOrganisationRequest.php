<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organisationId = $this->route('organisation')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('organisations', 'email')->ignore($organisationId)],
            'member_since' => ['sometimes', 'required', 'integer', 'min:1900', 'max:' . (int) date('Y')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}