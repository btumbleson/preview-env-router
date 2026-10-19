<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Cloud API
    |--------------------------------------------------------------------------
    |
    | The token needs to read applications and environments and manage
    | domains for every application whose previews this router handles.
    |
    */

    'cloud' => [
        'url' => env('CLOUD_API_URL', 'https://cloud.laravel.com/api'),
        'token' => env('CLOUD_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    |
    | "token" is the shared secret apps send (as a bearer token) when they
    | register a preview. "password" protects the status page.
    |
    */

    'token' => env('ROUTER_TOKEN'),

    'password' => env('ROUTER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Hostnames
    |--------------------------------------------------------------------------
    |
    | Each preview gets "{section}.{slug}.{root_domain}". The zone needs a
    | DNS-only "*.{root_domain}" CNAME to Cloud's origin, and the slug must
    | not match an existing record, which would block that wildcard.
    |
    | "sections" are used by the scheduled sync for previews that haven't
    | registered themselves; an app's own request may send its list instead.
    |
    */

    'root_domain' => env('ROUTER_ROOT_DOMAIN', 'laravel-demo.cloud'),

    'sections' => array_values(array_filter(explode(',', (string) env('ROUTER_SECTIONS', '')))),

    'max_sections' => 10,

    'max_slug_length' => 40,

];
