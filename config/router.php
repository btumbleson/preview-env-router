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
    | The shared secret previews send in the X-Router-Token header when they
    | register. The landing page itself is meant to sit behind Cloud's HTTP
    | basic auth, which uses the Authorization header, hence a custom one.
    |
    */

    'token' => env('ROUTER_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Hostnames
    |--------------------------------------------------------------------------
    |
    | Each preview gets "{section}.{slug}.{root_domain}". The zone needs a
    | DNS-only "*.{root_domain}" CNAME to Cloud's origin, and the slug must
    | not match an existing record, which would block that wildcard.
    |
    | The sections each application's previews get are edited on the landing
    | page and stored in the database.
    |
    */

    'root_domain' => env('ROUTER_ROOT_DOMAIN', 'laravel-demo.cloud'),

    'max_sections' => 10,

    'max_slug_length' => 40,

];
