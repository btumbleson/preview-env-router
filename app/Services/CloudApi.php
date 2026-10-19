<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Laravel Cloud API client for the endpoints the router uses.
 *
 * @see https://laravel.com/cloud/docs/api/introduction
 */
class CloudApi
{
    public function applications(): array
    {
        return $this->all('/applications');
    }

    public function environments(string $application): array
    {
        return $this->all("/applications/{$application}/environments");
    }

    public function domains(string $environment): array
    {
        return $this->all("/environments/{$environment}/domains");
    }

    public function findDomain(string $environment, string $hostname): ?array
    {
        return collect($this->all("/environments/{$environment}/domains?filter[name]=".urlencode($hostname)))
            ->firstWhere('attributes.name', $hostname);
    }

    /**
     * Add a single (non-wildcard) hostname that verifies in real time, relying
     * on the zone's DNS-only "*.{root}" CNAME rather than per-domain records.
     *
     * The API doesn't always return the new domain in the response body, so
     * fall back to looking it up by name.
     */
    public function createDomain(string $environment, string $hostname): ?array
    {
        $created = $this->request()->post("/environments/{$environment}/domains", [
            'name' => $hostname,
            'wildcard_enabled' => false,
            'allow_downtime' => true,
            'cloudflare_strategy' => 'dns',
            'verification_method' => 'real_time',
        ])->throw()->json('data');

        return $this->redact($created) ?: $this->findDomain($environment, $hostname);
    }

    public function verifyDomain(string $domain): void
    {
        $this->request()->post("/domains/{$domain}/verify")->throw();
    }

    /**
     * Fetch every page of a paginated collection endpoint.
     */
    protected function all(string $path): array
    {
        $items = [];
        $url = $path;

        do {
            $page = $this->redact($this->request()->get($url)->throw()->json());
            $items = [...$items, ...($page['data'] ?? [])];
            $url = $page['links']['next'] ?? null;
        } while ($url);

        return $items;
    }

    /**
     * Environment resources embed their variables (APP_KEY, API tokens, ...).
     * Nothing here needs them, so drop them from every response.
     */
    protected function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        unset($value['environment_variables']);

        return array_map($this->redact(...), $value);
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(config('router.cloud.url'))
            ->withToken(config('router.cloud.token'))
            ->acceptJson()
            ->timeout(20);
    }
}
