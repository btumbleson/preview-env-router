<?php

namespace App\Services;

use App\Models\SectionList;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gives preview environments "{section}.{slug}.{root}" custom domains, using
 * only the Cloud API: previews are discovered by polling, so the app being
 * previewed needs no knowledge of the router.
 */
class PreviewRegistrar
{
    public function __construct(protected CloudApi $cloud) {}

    /**
     * Every preview environment across the token's applications, keyed by id.
     *
     * @return array<string, array{application: array, environment: array}>
     */
    public function previews(): array
    {
        $previews = [];

        foreach ($this->cloud->applications() as $application) {
            foreach ($this->cloud->environments($application['id']) as $environment) {
                if ($environment['attributes']['created_from_automation'] ?? false) {
                    $previews[$environment['id']] = compact('application', 'environment');
                }
            }
        }

        return $previews;
    }

    /**
     * The sections configured on the landing page for the preview's app.
     *
     * @return list<string>
     */
    public function sections(array $preview): array
    {
        return SectionList::for($preview['application']['id']);
    }

    /**
     * A DNS-safe label for the preview, taken from the environment's slug
     * (Cloud derives it from the branch, e.g. "feat-internal-subdomain").
     */
    public function slug(array $environment): string
    {
        $slug = Str::of($environment['attributes']['slug'] ?? $environment['attributes']['name'] ?? $environment['id'])
            ->lower()
            ->replaceMatches('/[^a-z0-9-]+/', '-')
            ->replaceMatches('/-+/', '-')
            ->limit(config('router.max_slug_length'), '')
            ->trim('-')
            ->toString();

        // The vanity domain has its own DNS record, which would block the
        // wildcard underneath it, so never reuse its first label.
        $vanityLabel = Str::before((string) ($environment['attributes']['vanity_domain'] ?? ''), '.');

        return $slug === $vanityLabel ? "{$slug}-preview" : $slug;
    }

    /**
     * @return list<string>
     */
    public function hostnames(array $environment, array $sections): array
    {
        $slug = $this->slug($environment);

        return collect($sections)
            ->map(fn ($section) => strtolower($section).".{$slug}.".config('router.root_domain'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ensure each section has a custom domain on the environment. Existing
     * domains are left alone, so this is safe to run repeatedly.
     *
     * @return list<array{hostname: string, id: ?string, created: bool, hostname_status: mixed, ssl_status: mixed, origin_status: mixed}>
     */
    public function register(array $environment, array $sections): array
    {
        $existing = collect($this->cloud->domains($environment['id']))->keyBy('attributes.name');

        return collect($this->hostnames($environment, $sections))
            ->map(function ($hostname) use ($environment, $existing) {
                $domain = $existing->get($hostname);
                $created = false;

                if (! $domain) {
                    $domain = $this->cloud->createDomain($environment['id'], $hostname);
                    $created = true;
                }

                return [
                    'hostname' => $hostname,
                    'id' => $domain['id'] ?? null,
                    'created' => $created,
                    'hostname_status' => $domain['attributes']['hostname_status'] ?? 'pending',
                    'ssl_status' => $domain['attributes']['ssl_status'] ?? 'pending',
                    'origin_status' => $domain['attributes']['origin_status'] ?? 'pending',
                ];
            })
            ->all();
    }

    /**
     * Bring every preview environment in line with its application's
     * sections, and nudge domains that are still verifying.
     *
     * @return list<array{preview: string, added: list<string>, pending: int, error: ?string}>
     */
    public function sync(): array
    {
        return collect($this->previews())
            ->map(function ($preview) {
                $environment = $preview['environment'];
                $result = [
                    'preview' => $environment['attributes']['name'] ?? $environment['id'],
                    'added' => [],
                    'pending' => 0,
                    'error' => null,
                ];

                try {
                    if ($sections = $this->sections($preview)) {
                        $result['added'] = collect($this->register($environment, $sections))
                            ->where('created', true)
                            ->pluck('hostname')
                            ->all();
                    }

                    $result['pending'] = $this->nudge($environment);
                } catch (Throwable $e) {
                    $result['error'] = $e->getMessage();
                }

                return $result;
            })
            ->values()
            ->all();
    }

    /**
     * Ask Cloud to re-check every domain on the environment that isn't fully
     * verified yet. Returns how many were nudged.
     */
    public function nudge(array $environment): int
    {
        $pending = collect($this->cloud->domains($environment['id']))
            ->reject(fn ($domain) => collect($domain['attributes'])
                ->only(['hostname_status', 'ssl_status', 'origin_status'])
                ->every(fn ($status) => $status === 'verified'));

        $pending->each(fn ($domain) => rescue(fn () => $this->cloud->verifyDomain($domain['id']), report: false));

        return $pending->count();
    }
}
