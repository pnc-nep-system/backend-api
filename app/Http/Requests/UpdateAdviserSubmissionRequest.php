<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdviserSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Only NEP Coordinator and Admin can update submissions
        return $user && in_array($user->role, ['nep_admin', 'nep_coordinator']);
    }

    public function rules(): array
    {
        return [
            'assign_to_staff_user_id' => ['nullable', 'integer', 'exists:staff_users,id'],
            'section_profile' => ['nullable', 'string', 'max:1000'],
            'section_gaps' => ['nullable', 'string', 'max:1000'],
            'section_coordinators_notes' => ['nullable', 'string', 'max:1000'],
            'final_note_file' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'assign_to_staff_user_id.exists' => 'The selected staff user does not exist.',
        ];
    }
}