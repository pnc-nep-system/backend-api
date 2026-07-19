<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:nep_admin already enforced at route level
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:organisations,email'],
            'member_since' => ['required', 'integer', 'min:1900', 'max:' . (int) date('Y')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}