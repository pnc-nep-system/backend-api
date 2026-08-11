<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TestMailConfigurationRequest;
use App\Services\Mail\SmtpDiagnostics;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MailTestController extends Controller
{
    /**
     * Send a test email using either the SMTP settings supplied in the
     * request, or — for any field left out — whatever is currently
     * configured in .env (config/mail.php). This lets an admin verify the
     * live configuration with just a recipient address, or try a
     * prospective host/port/credentials combination without editing .env.
     *
     * Provider-agnostic by construction: this builds a plain SMTP DSN from
     * whatever host/port/encryption/username/password it's given (see
     * SmtpDiagnostics::buildDsn()) — there is no branch here, or anywhere
     * else in the mail-sending code, that treats Gmail differently from
     * Outlook, Zoho, or any other SMTP provider.
     */
    public function test(TestMailConfigurationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $config = [
            'host' => $data['host'] ?? config('mail.mailers.smtp.host'),
            'port' => $data['port'] ?? config('mail.mailers.smtp.port'),
            'encryption' => $data['encryption'] ?? null,
            'username' => $data['username'] ?? config('mail.mailers.smtp.username'),
            'password' => $data['password'] ?? config('mail.mailers.smtp.password'),
            'from_address' => $data['from_address'] ?? config('mail.from.address'),
            'from_name' => $data['from_name'] ?? config('mail.from.name'),
        ];

        if (empty($config['host'])) {
            return response()->json([
                'success' => false,
                'message' => 'No SMTP host configured — provide one, or set MAIL_HOST in the environment first.',
            ], 422);
        }

        if (empty($config['from_address'])) {
            return response()->json([
                'success' => false,
                'message' => 'No "From" address configured — provide one, or set MAIL_FROM_ADDRESS in the environment first.',
            ], 422);
        }

        // Logged for traceability, deliberately excludes the password.
        $context = [
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'] ?? 'auto',
            'username' => $config['username'] ? substr($config['username'], 0, 3) . '***' : null,
            'from' => $config['from_address'],
            'to' => $data['to'],
            'admin_id' => $request->user()?->id,
        ];

        try {
            SmtpDiagnostics::sendTestEmail($config, $data['to']);

            Log::info('SMTP test email sent successfully.', $context);

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully.',
            ]);
        } catch (\Throwable $e) {
            $diagnosis = SmtpDiagnostics::classify($e);

            Log::warning('SMTP test email failed.', $context + [
                'category' => $diagnosis['category'],
                'error' => $diagnosis['raw'],
            ]);

            return response()->json([
                'success' => false,
                'message' => $diagnosis['message'],
                'category' => $diagnosis['category'],
            ], 422);
        }
    }
}
