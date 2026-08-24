<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulate requests coming from the SPA frontend so Sanctum's
        // EnsureFrontendRequestsAreStateful treats them as stateful and
        // attaches the session — the same path real browser traffic takes.
        $this->withHeader('Referer', 'http://localhost');
    }
}
