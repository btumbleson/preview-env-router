<?php

namespace App\Http\Controllers;

use App\Models\SectionList;
use App\Services\CloudApi;
use App\Services\PreviewRegistrar;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Every application the token can see, with its section list (editable)
     * and its preview environments' domains, read live from the Cloud API.
     */
    public function index(PreviewRegistrar $registrar, CloudApi $cloud): View
    {
        try {
            $applications = $this->applications($registrar, $cloud);
        } catch (RequestException $e) {
            return view('dashboard', [
                'applications' => [],
                'error' => "Cloud API returned {$e->response->status()}: ".$e->response->json('message'),
            ]);
        }

        return view('dashboard', ['applications' => $applications, 'error' => null]);
    }

    /**
     * Just the preview lists, polled by the landing page every few seconds.
     * Domains still verifying get nudged, so their statuses keep moving.
     */
    public function previews(PreviewRegistrar $registrar, CloudApi $cloud): View
    {
        $applications = $this->applications($registrar, $cloud);

        $domains = collect($applications)
            ->flatMap(fn ($application) => $application['previews'])
            ->flatMap(fn ($preview) => $preview['domains']);

        foreach ($domains as $domain) {
            $registrar->nudgeDomain($domain);
        }

        return view('previews-fragment', ['applications' => $applications]);
    }

    protected function applications(PreviewRegistrar $registrar, CloudApi $cloud): array
    {
        $previews = collect($registrar->previews())->groupBy('application.id');

        return collect($cloud->applications())
            ->map(fn ($application) => [
                'id' => $application['id'],
                'name' => $application['attributes']['name'] ?? $application['id'],
                'sections' => SectionList::for($application['id']),
                'previews' => $previews->get($application['id'], collect())
                    ->map(fn ($preview) => $preview + [
                        'slug' => $registrar->slug($preview['environment']),
                        'domains' => $cloud->domains($preview['environment']['id']),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * The same sync for curl: called from a preview automation's initial
     * deploy commands, so a new preview gets its domains as it deploys.
     */
    public function syncApi(PreviewRegistrar $registrar): JsonResponse
    {
        $results = $registrar->sync();

        return response()->json([
            'previews' => count($results),
            'added' => collect($results)->flatMap(fn ($result) => $result['added'])->values(),
            'errors' => collect($results)->filter(fn ($result) => $result['error'])
                ->map(fn ($result) => "{$result['preview']}: {$result['error']}")->values(),
        ]);
    }

    public function sync(PreviewRegistrar $registrar): RedirectResponse
    {
        $results = collect($registrar->sync());
        $added = $results->flatMap(fn ($result) => $result['added']);
        $errors = $results->filter(fn ($result) => $result['error'])
            ->map(fn ($result) => "{$result['preview']}: {$result['error']}");

        return to_route('dashboard')
            ->with('status', $added->isEmpty()
                ? "Synced {$results->count()} preview(s). Nothing new to add."
                : "Synced {$results->count()} preview(s). Added {$added->implode(', ')}.")
            ->with('sync_errors', $errors->values()->all());
    }

    public function updateSections(Request $request, string $application): RedirectResponse
    {
        $request->validate([
            'application_name' => ['nullable', 'string', 'max:255'],
            'sections' => ['nullable', 'string', 'max:1000'],
        ]);

        $sections = SectionList::parse((string) $request->input('sections'));

        validator(['sections' => $sections], [
            'sections' => ['array', 'max:'.config('router.max_sections')],
            'sections.*' => ['max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/'],
        ], [
            'sections.*.regex' => 'Sections may only contain letters, numbers and dashes, like "admin" or "back-office".',
        ])->validateWithBag($application);

        SectionList::updateOrCreate(
            ['application_id' => $application],
            ['application_name' => $request->input('application_name'), 'sections' => $sections],
        );

        return to_route('dashboard')->with('status', 'Sections saved. New previews get them when they deploy; use Sync now for existing ones.');
    }
}
