<?php

namespace App\Http\Controllers;

use App\Models\SectionList;
use App\Services\CloudApi;
use App\Services\PreviewRegistrar;
use Illuminate\Http\Client\RequestException;
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
            $previews = collect($registrar->previews())->groupBy('application.id');

            $applications = collect($cloud->applications())
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
        } catch (RequestException $e) {
            return view('dashboard', [
                'applications' => [],
                'error' => "Cloud API returned {$e->response->status()}: ".$e->response->json('message'),
            ]);
        }

        return view('dashboard', ['applications' => $applications, 'error' => null]);
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

        return to_route('dashboard')->with('status', 'Sections saved. New and existing previews get them on their next registration or sync.');
    }
}
