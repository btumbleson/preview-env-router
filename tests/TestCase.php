<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The app pins the file cache; keep tests from sharing state through it.
        config(['cache.default' => 'array']);
    }
}
