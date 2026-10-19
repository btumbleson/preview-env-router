<?php

namespace App\Http\Controllers;

use App\Services\PreviewRegistrar;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterPreviewController extends Controller
{
    /**
     * Called by a preview environment (from its deploy command) to get a
     * custom domain for each section configured for its application.
     */
    public function __invoke(Request $request, PreviewRegistrar $registrar): JsonResponse
    {
        $validated = $request->validate([
            'app_url' => ['required', 'string', 'max:255'],
        ]);

        try {
            $preview = $registrar->findByUrl($validated['app_url']);

            if (! $preview) {
                return response()->json([
                    'message' => "No preview environment has the vanity domain for {$validated['app_url']}.",
                ], 404);
            }

            $application = $preview['application']['attributes']['name'] ?? $preview['application']['id'];

            if (! $sections = $registrar->sections($preview)) {
                return response()->json([
                    'message' => "No sections are configured for {$application} on the router.",
                ], 422);
            }

            $domains = $registrar->register($preview['environment'], $sections);
        } catch (RequestException $e) {
            return response()->json([
                'message' => "Cloud API returned {$e->response->status()}.",
                'cloud' => $e->response->json('message'),
            ], 502);
        }

        return response()->json([
            'application' => $application,
            'environment' => $preview['environment']['id'],
            'slug' => $registrar->slug($preview['environment']),
            'domains' => $domains,
        ]);
    }
}
