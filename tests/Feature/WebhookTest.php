<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\CustomDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    // In Docker the mock reaches the app as http://app:3000.
    protected const URL = 'http://app/webhooks/saascustomdomains';

    protected bool $seed = true;

    protected CustomDomain $domain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domain = Board::firstWhere('slug', 'acme')->customDomains()
            ->create(['host' => 'jobs.acme.com', 'provider_uuid' => 'dom_1']);
    }

    protected function event(string $event, string $uuid = 'dom_1', string $token = 'test_webhook_token')
    {
        return $this->postJson(self::URL."?token={$token}", ['event' => $event, 'domain' => ['uuid' => $uuid, 'host' => 'jobs.acme.com']]);
    }

    public function test_needs_the_right_token(): void
    {
        $this->event('domain_dns_proxy_record_valid', token: 'guess')->assertUnauthorized();
        $this->assertSame('pending', $this->domain->fresh()->status);
    }

    public function test_rejects_everything_when_no_token_is_configured(): void
    {
        config(['services.saascustomdomains.webhook_token' => null]);

        $this->event('domain_dns_proxy_record_valid', token: '')->assertUnauthorized();
    }

    public function test_dns_and_certificate_events_take_a_domain_live(): void
    {
        $this->event('domain_dns_proxy_record_valid')->assertNoContent();
        $this->assertSame('DNS OK, waiting for HTTPS', $this->domain->fresh()->statusLabel());

        $this->event('tls_certificate_issued')->assertNoContent();
        $this->event('tls_certificate_issued')->assertNoContent();
        $this->assertSame('Live', $this->domain->fresh()->statusLabel());

        $this->event('domain_dns_proxy_record_invalid')->assertNoContent();
        $this->assertSame('Waiting for DNS', $this->domain->fresh()->statusLabel());
    }

    public function test_acknowledges_unknown_domains(): void
    {
        $this->event('domain_dns_proxy_record_valid', uuid: 'someone_else')->assertNoContent();
    }
}
