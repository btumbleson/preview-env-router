<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouterTest extends TestCase
{
    protected const API = 'https://cloud.laravel.com/api';

    protected const VANITY = 'subdomain-test-feat-internal-subdom-1igu0v.laravel-demo.cloud';

    /** @var array<string, list<array>> Domains per environment, mutated by fake POSTs. */
    protected array $domains = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function environment(string $id, bool $preview, string $slug, string $vanity): array
    {
        return ['id' => $id, 'type' => 'environments', 'attributes' => [
            'name' => $slug,
            'slug' => $slug,
            'vanity_domain' => $vanity,
            'created_from_automation' => $preview,
            'environment_variables' => [['key' => 'CLOUD_API_TOKEN', 'value' => 'leaked']],
        ]];
    }

    protected function domain(string $name, string $status = 'pending'): array
    {
        return ['id' => 'dom-'.md5($name), 'type' => 'domains', 'attributes' => [
            'name' => $name,
            'hostname_status' => $status,
            'ssl_status' => $status,
            'origin_status' => $status,
        ]];
    }

    /**
     * A fake Cloud API with one app: a production environment and a preview.
     */
    protected function fakeCloud(?string $createdBody = 'data'): void
    {
        $this->domains += ['env-prod' => [], 'env-preview' => []];

        Http::fake(function (Request $request) use ($createdBody) {
            $path = str_replace(self::API, '', strtok($request->url(), '?'));

            return match (true) {
                $path === '/applications' => Http::response(['data' => [
                    ['id' => 'app-1', 'type' => 'applications', 'attributes' => ['name' => 'subdomain-test']],
                ]]),
                $path === '/applications/app-1/environments' => Http::response(['data' => [
                    $this->environment('env-prod', false, 'production', 'subdomain-test.laravel-demo.cloud'),
                    $this->environment('env-preview', true, 'feat-internal-subdomain', self::VANITY),
                ]]),
                $request->method() === 'POST' && preg_match('#^/environments/([^/]+)/domains$#', $path, $m) === 1 => (function () use ($request, $m, $createdBody) {
                    $domain = $this->domain($request['name']);
                    $this->domains[$m[1]][] = $domain;

                    return Http::response($createdBody ? ['data' => $domain] : null, 201);
                })(),
                preg_match('#^/environments/([^/]+)/domains$#', $path, $m) === 1 => Http::response(['data' => collect($this->domains[$m[1]] ?? [])
                    ->when(str_contains(urldecode($request->url()), 'filter[name]='), fn ($c) => $c->where('attributes.name', $request['filter']['name'] ?? null))
                    ->values()->all()]),
                preg_match('#^/domains/[^/]+/verify$#', $path) === 1 => Http::response([]),
                default => Http::response(['message' => "Unexpected {$request->method()} {$path}"], 500),
            };
        });
    }

    protected function register(array $payload, string $token = 'router-secret')
    {
        return $this->postJson('/api/previews', $payload, ['Authorization' => "Bearer {$token}"]);
    }

    public function test_register_requires_the_router_token(): void
    {
        $this->register(['app_url' => 'https://'.self::VANITY], 'wrong')->assertUnauthorized();
        $this->postJson('/api/previews', ['app_url' => 'https://'.self::VANITY])->assertUnauthorized();
    }

    public function test_register_adds_a_domain_per_section_for_the_matching_preview(): void
    {
        $this->fakeCloud();

        $this->register(['app_url' => 'https://'.self::VANITY, 'sections' => ['admin', 'internal']])
            ->assertOk()
            ->assertJsonPath('environment', 'env-preview')
            ->assertJsonPath('slug', 'feat-internal-subdomain')
            ->assertJsonPath('domains.0.hostname', 'admin.feat-internal-subdomain.laravel-demo.cloud')
            ->assertJsonPath('domains.0.created', true)
            ->assertJsonPath('domains.1.hostname', 'internal.feat-internal-subdomain.laravel-demo.cloud');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['name'] === 'internal.feat-internal-subdomain.laravel-demo.cloud'
            && $request['wildcard_enabled'] === false
            && $request['cloudflare_strategy'] === 'dns'
            && $request['verification_method'] === 'real_time'
            && str_contains($request->url(), '/environments/env-preview/domains'));
    }

    public function test_register_is_idempotent(): void
    {
        $this->fakeCloud();
        $payload = ['app_url' => 'https://'.self::VANITY, 'sections' => ['admin']];

        $this->register($payload)->assertJsonPath('domains.0.created', true);
        $this->register($payload)->assertJsonPath('domains.0.created', false);

        $this->assertCount(1, $this->domains['env-preview']);
    }

    public function test_register_finds_the_domain_when_cloud_returns_no_body(): void
    {
        $this->fakeCloud(createdBody: null);

        $this->register(['app_url' => 'https://'.self::VANITY, 'sections' => ['admin']])
            ->assertOk()
            ->assertJsonPath('domains.0.id', 'dom-'.md5('admin.feat-internal-subdomain.laravel-demo.cloud'));
    }

    public function test_register_falls_back_to_configured_sections(): void
    {
        $this->fakeCloud();

        $this->register(['app_url' => 'https://'.self::VANITY])
            ->assertOk()
            ->assertJsonCount(2, 'domains')
            ->assertJsonPath('domains.1.hostname', 'booking.feat-internal-subdomain.laravel-demo.cloud');
    }

    public function test_register_never_touches_non_preview_environments(): void
    {
        $this->fakeCloud();

        $this->register(['app_url' => 'https://subdomain-test.laravel-demo.cloud', 'sections' => ['admin']])
            ->assertNotFound();

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_register_rejects_invalid_sections(): void
    {
        $this->register(['app_url' => 'https://'.self::VANITY, 'sections' => ['admin.evil']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sections.0');
    }

    public function test_slug_never_reuses_the_vanity_label(): void
    {
        $registrar = app(\App\Services\PreviewRegistrar::class);

        $this->assertSame('pr-1-preview', $registrar->slug($this->environment('e', true, 'pr-1', 'pr-1.laravel-demo.cloud')));
        $this->assertSame('feat-some-branch', $registrar->slug($this->environment('e', true, 'Feat/Some_Branch', 'x.laravel-demo.cloud')));
        $this->assertLessThanOrEqual(40, strlen($registrar->slug($this->environment('e', true, str_repeat('long-', 20), 'x.laravel-demo.cloud'))));
    }

    public function test_sync_registers_configured_sections_and_nudges_pending_domains(): void
    {
        $this->fakeCloud();

        $this->artisan('previews:sync')
            ->expectsOutputToContain('added admin.feat-internal-subdomain.laravel-demo.cloud')
            ->expectsOutputToContain('re-verifying 2 pending domain(s)')
            ->assertSuccessful();

        $this->assertSame([], $this->domains['env-prod']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/verify'));
    }

    public function test_dashboard_requires_the_password(): void
    {
        $this->get('/')->assertUnauthorized();
        $this->get('/', ['PHP_AUTH_USER' => 'x', 'PHP_AUTH_PW' => 'nope'])->assertUnauthorized();
    }

    public function test_dashboard_lists_previews_without_leaking_variables(): void
    {
        $this->fakeCloud();
        $this->domains['env-preview'][] = $this->domain('admin.feat-internal-subdomain.laravel-demo.cloud', 'verified');

        $this->get('/', ['PHP_AUTH_USER' => 'x', 'PHP_AUTH_PW' => 'dash-secret'])
            ->assertOk()
            ->assertSee('feat-internal-subdomain')
            ->assertSee('admin.feat-internal-subdomain.laravel-demo.cloud')
            ->assertSee('ssl: verified')
            ->assertDontSee('production ·', false)
            ->assertDontSee('leaked');
    }
}
