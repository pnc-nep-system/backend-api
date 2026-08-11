<?php

namespace App\Services\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Provider-agnostic SMTP support: builds a one-off Symfony Mailer transport
 * from arbitrary connection details (used by the "Test Email Configuration"
 * feature to try a prospective config without touching .env), and classifies
 * transport failures into a human-readable reason so admins can tell
 * "wrong password" apart from "wrong host" apart from "TLS problem" instead
 * of a single opaque "email failed" log line.
 *
 * This intentionally never branches on which provider is configured — a DSN
 * built from host/port/username/password/encryption works identically for
 * Gmail, Microsoft 365, Zoho, a company Exchange server, or anything else
 * that speaks SMTP. Laravel's own config/mail.php works the same way; this
 * class exists only to (a) test settings that aren't in .env yet and
 * (b) turn a raw Symfony exception into something actionable.
 */
class SmtpDiagnostics
{
    /**
     * Build a Symfony Mailer DSN from generic SMTP settings. `encryption`
     * accepts: 'tls' / 'starttls' (opportunistic TLS on the given port —
     * correct for the near-universal port 587), 'ssl' (implicit TLS from
     * connection start — correct for port 465), or null/'none' (plain,
     * still upgrades opportunistically if the server offers STARTTLS,
     * matching standard SMTP client behaviour).
     */
    public static function buildDsn(array $config): string
    {
        $encryption = strtolower((string) ($config['encryption'] ?? ''));
        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';

        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        $auth = '';
        if ($username !== '') {
            $auth = rawurlencode($username);
            if ($password !== '') {
                $auth .= ':' . rawurlencode($password);
            }
            $auth .= '@';
        }

        $host = $config['host'];
        $port = $config['port'] ?? 587;

        return "{$scheme}://{$auth}{$host}:{$port}";
    }

    /**
     * Send a short test email using the given (not necessarily persisted)
     * SMTP settings. Throws on failure — callers should catch and pass the
     * exception to classify().
     */
    public static function sendTestEmail(array $config, string $toEmail): void
    {
        $transport = Transport::fromDsn(self::buildDsn($config));
        $mailer = new Mailer($transport);

        $email = (new Email())
            ->from(new Address($config['from_address'], $config['from_name'] ?? ''))
            ->to($toEmail)
            ->subject('NEP System — Test Email')
            ->text(
                "This is a test email confirming your SMTP configuration is working.\n\n" .
                "Host: {$config['host']}\n" .
                'Port: ' . ($config['port'] ?? 587) . "\n" .
                'Encryption: ' . ($config['encryption'] ?? 'auto') . "\n"
            );

        $mailer->send($email);
    }

    /**
     * Classify a mail-sending failure into a stable category + a message
     * safe to show an admin. Never includes the password — only the raw
     * transport exception message, which Symfony populates from the SMTP
     * server's own response text/codes, not from the credentials used.
     *
     * @return array{category: string, message: string, raw: string}
     */
    public static function classify(Throwable $e): array
    {
        $raw = $e->getMessage();
        $haystack = strtolower($raw);

        $category = 'unknown';
        $message = 'Unable to send the email. See the server log for details.';

        if (! $e instanceof TransportExceptionInterface) {
            // Not a transport-layer failure at all (e.g. bad "to" address,
            // view rendering error) — don't misreport it as an SMTP problem.
            return [
                'category' => 'application',
                'message' => 'The email could not be built or sent: ' . $raw,
                'raw' => $raw,
            ];
        }

        if (str_contains($haystack, 'smtpclientauthentication is disabled') || str_contains($haystack, '5.7.139')) {
            $category = 'auth_m365_disabled';
            $message = 'Microsoft 365 rejected the login because SMTP AUTH is disabled for this mailbox/tenant. '
                . 'An Exchange admin must enable "Authenticated SMTP" for this account (Microsoft 365 admin center → '
                . 'user → Mail → Manage email apps), or a mailbox-level app password / modern auth must be used instead.';
        } elseif (str_contains($haystack, 'username and password not accepted') || str_contains($haystack, '5.7.8')) {
            $category = 'auth_gmail_rejected';
            $message = 'The mail server rejected the username/password. For Gmail/Google Workspace, confirm you are '
                . 'using an App Password (not the account password) and that 2-Step Verification is enabled.';
        } elseif (str_contains($haystack, 'authentication') || str_contains($haystack, ' 535 ') || str_contains($haystack, ' 530 ')) {
            $category = 'auth';
            $message = 'Authentication failed — the SMTP username or password is incorrect, or this account is not '
                . 'permitted to send via this server.';
        } elseif (str_contains($haystack, 'getaddrinfo') || str_contains($haystack, 'name or service not known') || str_contains($haystack, 'could not resolve host')) {
            // Checked before the generic "connection could not be established"
            // wrapper below, since Symfony includes that phrase for this case too.
            $category = 'invalid_host';
            $message = 'The SMTP host could not be resolved — double-check the hostname for typos.';
        } elseif (str_contains($haystack, 'ssl') || str_contains($haystack, 'tls') || str_contains($haystack, 'crypto') || str_contains($haystack, 'certificate')) {
            // Also checked before the generic connection-failure branch —
            // Symfony wraps TLS handshake failures in the same
            // "Connection could not be established" phrasing.
            $category = 'tls';
            $message = 'A TLS/SSL negotiation error occurred — the encryption setting likely doesn\'t match what this '
                . 'port expects (587 usually wants STARTTLS/"tls", 465 wants implicit TLS/"ssl").';
        } elseif (str_contains($haystack, 'timed out') || str_contains($haystack, 'timeout')) {
            $category = 'timeout';
            $message = 'The connection to the SMTP server timed out — the host may be unreachable, or the port is '
                . 'blocked between this server and the mail provider.';
        } elseif (str_contains($haystack, 'could not connect') || str_contains($haystack, 'connection refused') || str_contains($haystack, 'connection could not be established')) {
            $category = 'connection';
            $message = 'Could not connect to the SMTP server — check that the host and port are correct and that '
                . 'outbound traffic on this port is not blocked by a firewall.';
        } elseif (str_contains($haystack, 'relay') && str_contains($haystack, 'denied')) {
            $category = 'relay_denied';
            $message = 'The server refused to relay this message — the "From" address is probably not one this '
                . 'SMTP account is authorized to send as.';
        } elseif (preg_match('/\b55[0-4]\b/', $raw) || str_contains($haystack, 'recipient') || str_contains($haystack, 'mailbox unavailable')) {
            $category = 'recipient_rejected';
            $message = 'The recipient address was rejected by the destination mail server. Double-check the test '
                . 'recipient address.';
        } elseif (str_contains($haystack, 'sender address rejected') || str_contains($haystack, '553')) {
            $category = 'sender_rejected';
            $message = 'The "From" address was rejected — many providers require the From address to match the '
                . 'authenticated account (or a verified alias/domain).';
        }

        return [
            'category' => $category,
            'message' => $message,
            'raw' => $raw,
        ];
    }
}
