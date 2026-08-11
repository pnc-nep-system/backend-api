<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestMailConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every SMTP field is optional and independently falls back to the
     * currently configured mailer (see MailTestController) — an admin can
     * test "what's live right now" with just a recipient, or override any
     * subset of fields to try a prospective configuration before saving it
     * anywhere.
     */
    public function rules(): array
    {
        return [
            'host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['sometimes', 'nullable', Rule::in(['tls', 'starttls', 'ssl', 'none'])],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'to' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'to.required' => 'Enter a test recipient address to send the test email to.',
            'port.between' => 'Port must be a valid TCP port number (1-65535).',
            'encryption.in' => 'Encryption must be one of: tls, ssl, or none.',
        ];
    }
}
