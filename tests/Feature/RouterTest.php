<?php

namespace Tests\Feature;

use App\Models\SectionList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouterTest extends TestCase
{
    use RefreshDatabase;

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

    protected function sections(array $sections): void
    {
        SectionList::create(['application_id' => 'app-1', 'application_name' => 'subdomain-test', 'sections' => $sections]);
    }

    public function test_sync_adds_a_domain_per_configured_section_to_each_preview(): void
    {
        $this->fakeCloud();
        $this->sections(['admin', 'internal']);

        $this->artisan('previews:sync')
            ->expectsOutputToContain('added admin.feat-internal-subdomain.laravel-demo.cloud')
            ->expectsOutputToContain('added internal.feat-internal-subdomain.laravel-demo.cloud')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/domains')
            && $request['name'] === 'internal.feat-internal-subdomain.laravel-demo.cloud'
            && $request['wildcard_enabled'] === false
            && $request['cloudflare_strategy'] === 'dns'
            && $request['verification_method'] === 'real_time'
            && str_contains($request->url(), '/environments/env-preview/domains'));
    }

    public function test_sync_is_idempotent(): void
    {
        $this->fakeCloud();
        $this->sections(['admin']);

        $this->artisan('previews:sync')->expectsOutputToContain('added admin.')->assertSuccessful();
        $this->artisan('previews:sync')->doesntExpectOutputToContain('added')->assertSuccessful();

        $this->assertCount(1, $this->domains['env-preview']);
    }

    public function test_sync_handles_cloud_returning_no_body_on_create(): void
    {
        $this->fakeCloud(createdBody: null);
        $this->sections(['admin']);

        $this->artisan('previews:sync')->expectsOutputToContain('added admin.')->assertSuccessful();

        $this->assertCount(1, $this->domains['env-preview']);
    }

    public function test_sync_adds_nothing_without_configured_sections(): void
    {
        $this->fakeCloud();

        $this->artisan('previews:sync')->doesntExpectOutputToContain('added')->assertSuccessful();

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/domains'));
    }

    public function test_sync_never_touches_non_preview_environments(): void
    {
        $this->fakeCloud();
        $this->sections(['admin']);

        $this->artisan('previews:sync')->assertSuccessful();

        $this->assertSame([], $this->domains['env-prod']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'env-prod'));
    }

    public function test_sync_is_not_scheduled(): void
    {
        $this->assertEmpty(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
    }

    public function test_curl_can_trigger_a_sync_without_a_csrf_token(): void
    {
        $this->fakeCloud();
        $this->sections(['admin', 'internal']);

        $this->withMiddleware()
            ->call('POST', '/api/sync', server: ['HTTP_ACCEPT' => '*/*'])
            ->assertOk()
            ->assertExactJson([
                'previews' => 1,
                'added' => [
                    'admin.feat-internal-subdomain.laravel-demo.cloud',
                    'internal.feat-internal-subdomain.laravel-demo.cloud',
                ],
                'errors' => [],
            ]);

        $this->postJson('/api/sync')->assertOk()->assertJsonPath('added', []);
    }

    public function test_landing_page_shows_the_curl_command(): void
    {
        $this->fakeCloud();

        $this->get('/')->assertOk()->assertSee('curl -fsS -X POST '.route('api.sync').' || true', false);
    }

    public function test_sync_now_button_runs_a_sync(): void
    {
        $this->fakeCloud();
        $this->sections(['admin']);

        $this->post('/sync')
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Synced 1 preview(s). Added admin.feat-internal-subdomain.laravel-demo.cloud.');
    }

    public function test_slug_never_reuses_the_vanity_label(): void
    {
        $registrar = app(\App\Services\PreviewRegistrar::class);

        $this->assertSame('pr-1-preview', $registrar->slug($this->environment('e', true, 'pr-1', 'pr-1.laravel-demo.cloud')));
        $this->assertSame('feat-some-branch', $registrar->slug($this->environment('e', true, 'Feat/Some_Branch', 'x.laravel-demo.cloud')));
        $this->assertLessThanOrEqual(40, strlen($registrar->slug($this->environment('e', true, str_repeat('long-', 20), 'x.laravel-demo.cloud'))));
    }

    public function test_sync_nudges_pending_domains(): void
    {
        $this->fakeCloud();
        $this->sections(['admin', 'booking']);

        $this->artisan('previews:sync')
            ->expectsOutputToContain('re-verifying 2 pending domain(s)')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/verify'));
    }

    public function test_landing_page_lists_apps_sections_and_previews_without_leaking_variables(): void
    {
        $this->fakeCloud();
        $this->sections(['admin', 'internal']);
        $this->domains['env-preview'][] = $this->domain('admin.feat-internal-subdomain.laravel-demo.cloud', 'verified');

        $this->get('/')
            ->assertOk()
            ->assertSee('subdomain-test')
            ->assertSee('value="admin, internal"', false)
            ->assertSee('admin.feat-internal-subdomain.laravel-demo.cloud')
            ->assertSee('ssl: verified')
            ->assertDontSee('leaked');
    }

    public function test_sections_can_be_saved_from_the_landing_page(): void
    {
        $this->put('/applications/app-1/sections', [
            'application_name' => 'subdomain-test',
            'sections' => 'Admin, booking  portal,admin',
        ])->assertRedirect('/');

        $this->assertSame(['admin', 'booking', 'portal'], SectionList::for('app-1'));

        $this->put('/applications/app-1/sections', ['sections' => 'internal'])->assertRedirect('/');
        $this->assertSame(['internal'], SectionList::for('app-1'));
        $this->assertSame(1, SectionList::count());
    }

    public function test_invalid_sections_are_rejected(): void
    {
        $this->from('/')->put('/applications/app-1/sections', ['sections' => 'admin, bad.label'])
            ->assertRedirect('/')
            ->assertSessionHasErrorsIn('app-1');

        $this->assertSame([], SectionList::for('app-1'));
    }
}
