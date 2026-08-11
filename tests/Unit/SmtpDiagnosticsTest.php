<?php

namespace Tests\Unit;

use App\Services\Mail\SmtpDiagnostics;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Pure unit tests for the provider-agnostic SMTP helper — no network access,
 * no app boot required. Confirms the DSN builder produces a correct,
 * safely-encoded DSN for any host/port/encryption combination (not just
 * Gmail's), and that exception classification categorizes common SMTP
 * failure modes without ever surfacing the password.
 */
class SmtpDiagnosticsTest extends TestCase
{
    public function test_build_dsn_for_starttls_on_587(): void
    {
        $dsn = SmtpDiagnostics::buildDsn([
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@company.com',
            'password' => 'secret',
        ]);

        $this->assertSame('smtp://user%40company.com:secret@smtp.office365.com:587', $dsn);
    }

    public function test_build_dsn_for_implicit_tls_on_465(): void
    {
        $dsn = SmtpDiagnostics::buildDsn([
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'user@gmail.com',
            'password' => 'app-password',
        ]);

        $this->assertStringStartsWith('smtps://', $dsn);
        $this->assertStringContainsString('smtp.gmail.com:465', $dsn);
    }

    public function test_build_dsn_url_encodes_special_characters_in_credentials(): void
    {
        // A password containing DSN-significant characters (@, :, /) must not
        // break DSN parsing or leak into the host/port portion.
        $dsn = SmtpDiagnostics::buildDsn([
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'user@example.com',
            'password' => 'p@ss:word/withslash',
        ]);

        $this->assertSame(
            'smtp://user%40example.com:p%40ss%3Aword%2Fwithslash@smtp.example.com:587',
            $dsn
        );
    }

    public function test_build_dsn_without_credentials(): void
    {
        $dsn = SmtpDiagnostics::buildDsn([
            'host' => 'localhost',
            'port' => 25,
        ]);

        $this->assertSame('smtp://localhost:25', $dsn);
    }

    public function test_build_dsn_is_provider_agnostic(): void
    {
        // Same code path, different host — proves there is no per-provider branch.
        $gmail = SmtpDiagnostics::buildDsn(['host' => 'smtp.gmail.com', 'port' => 587, 'username' => 'a', 'password' => 'b']);
        $outlook = SmtpDiagnostics::buildDsn(['host' => 'smtp.office365.com', 'port' => 587, 'username' => 'a', 'password' => 'b']);
        $custom = SmtpDiagnostics::buildDsn(['host' => 'mail.mycompany.com', 'port' => 587, 'username' => 'a', 'password' => 'b']);

        $this->assertSame('smtp://a:b@smtp.gmail.com:587', $gmail);
        $this->assertSame('smtp://a:b@smtp.office365.com:587', $outlook);
        $this->assertSame('smtp://a:b@mail.mycompany.com:587', $custom);
    }

    public function test_classify_detects_microsoft_365_smtp_auth_disabled(): void
    {
        $e = new TransportException('Expected response code 235 but got code 535, with message 535 5.7.139 Authentication unsuccessful, SmtpClientAuthentication is disabled for the Tenant.');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('auth_m365_disabled', $result['category']);
        $this->assertStringContainsString('Exchange admin', $result['message']);
    }

    public function test_classify_detects_gmail_credential_rejection(): void
    {
        $e = new TransportException('Expected response code 235 but got code 534, with message 534-5.7.9 Application-specific password required. 535 5.7.8 Username and Password not accepted.');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('auth_gmail_rejected', $result['category']);
        $this->assertStringContainsString('App Password', $result['message']);
    }

    public function test_classify_detects_generic_authentication_failure(): void
    {
        $e = new TransportException('Expected response code 235 but got code 535, with message 535 Authentication failed');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('auth', $result['category']);
    }

    public function test_classify_detects_connection_refused(): void
    {
        $e = new TransportException('Connection could not be established with host "smtp.example.com:587": Connection refused');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('connection', $result['category']);
    }

    public function test_classify_detects_unresolvable_host(): void
    {
        $e = new TransportException('php_network_getaddresses: getaddrinfo for smtp.doesnotexist.invalid failed: Name or service not known');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('invalid_host', $result['category']);
    }

    public function test_classify_detects_tls_negotiation_failure(): void
    {
        $e = new TransportException('Connection could not be established: stream_socket_enable_crypto(): SSL operation failed with code 1. OpenSSL Error messages: error:certificate verify failed');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('tls', $result['category']);
    }

    public function test_classify_never_includes_password_and_falls_back_gracefully(): void
    {
        $e = new TransportException('Some brand-new SMTP error format we have never seen before');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('unknown', $result['category']);
        $this->assertStringNotContainsString('password', strtolower($result['message']));
    }

    public function test_classify_distinguishes_non_transport_exceptions(): void
    {
        // A validation/view-rendering error is not an SMTP problem and
        // shouldn't be mislabeled as one.
        $e = new \InvalidArgumentException('Address in mailbox given [not-an-email] does not comply with RFC 2822');

        $result = SmtpDiagnostics::classify($e);

        $this->assertSame('application', $result['category']);
    }
}
