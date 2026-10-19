<?php

namespace App\Http\Controllers;

use App\Services\CloudApi;
use App\Services\PreviewRegistrar;
use Illuminate\Http\Client\RequestException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Every preview environment with its router-managed hostnames and their
     * verification statuses, read live from the Cloud API.
     */
    public function __invoke(PreviewRegistrar $registrar, CloudApi $cloud): View
    {
        try {
            $previews = collect($registrar->previews())
                ->map(fn ($preview) => $preview + [
                    'slug' => $registrar->slug($preview['environment']),
                    'domains' => $cloud->domains($preview['environment']['id']),
                ])
                ->all();
        } catch (RequestException $e) {
            return view('dashboard', [
                'previews' => [],
                'error' => "Cloud API returned {$e->response->status()}: ".$e->response->json('message'),
            ]);
        }

        return view('dashboard', ['previews' => $previews, 'error' => null]);
    }
}
