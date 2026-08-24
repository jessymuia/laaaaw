<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * Real bug this suite exists to catch, found the hard way against a live
 * MySQL instance (not reproducible via php -l, static analysis, or any
 * check against the schema — this is a pure PHP runtime visibility rule):
 *
 * Every list endpoint's row formatter was declared `private function
 * formatRow(...)`, then handed to paginatedOrFull() as a callable array
 * — `[$this, 'formatRow']`. That callable gets invoked by Laravel's
 * internal Illuminate\Support\Collection::map()/transform(), which lives
 * in a completely unrelated class. PHP's visibility rules mean a
 * `private` method is only invocable from the exact class that declares
 * it — calling it from outside (even via a callable array naming the
 * right object) throws, and Laravel's base Controller::__call() turns
 * that into "Method ...::formatRow does not exist." on every single
 * list endpoint, with a 500 response.
 *
 * Calling $this->formatRow($row) directly from inside the same
 * controller (e.g. from store()/update(), to format a single freshly
 * created/updated record) stays inside the class and was never affected
 * — which is exactly why this bug hid behind working create/update
 * responses while every list view silently 500'd.
 *
 * The fix: formatRow must be `public`. This suite hits every affected
 * endpoint, both with and without ?page=, since both paginatedOrFull()
 * branches (Collection::map() and Collection::transform()) have the
 * identical externally-invoked-callable problem.
 */
class ListEndpointsFormatRowTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    /**
     * @dataProvider listEndpoints
     */
    public function test_list_endpoint_does_not_500_when_paginated(string $uri): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson($uri.'?page=1&per_page=10');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data.data') ?? $response->json('data'));
    }

    /**
     * @dataProvider listEndpoints
     */
    public function test_list_endpoint_does_not_500_without_pagination_params(string $uri): void
    {
        // The non-paginated branch (no ?page=) uses Collection::map()
        // instead of ::transform() — a different method, but invoked the
        // exact same externally-callable way, so it needed the exact
        // same fix and deserves its own regression coverage.
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson($uri);

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    public static function listEndpoints(): array
    {
        return [
            'clients' => ['/api/clients'],
            'users' => ['/api/users'],
            'court types' => ['/api/courttypes'],
            'courts' => ['/api/courts'],
            'cases' => ['/api/cases'],
            'tasks' => ['/api/tasks'],
            'expenses' => ['/api/expenses'],
            'expense categories' => ['/api/expense-categories'],
            'hearings' => ['/api/hearings'],
            'hearing types' => ['/api/hearingtypes'],
            'invoices' => ['/api/invoices'],
            'time entries' => ['/api/time-entries'],
            'payments' => ['/api/payments'],
            'roles' => ['/api/roles'],
        ];
    }
}
