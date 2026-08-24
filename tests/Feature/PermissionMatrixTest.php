<?php

namespace Tests\Feature;

use App\Models\Cases;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesTestUsers;
use Tests\TestCase;

/**
 * SEC-3 regression guard: this codebase previously had controllers
 * checking the *wrong* permission constant entirely (e.g. hearings and
 * cases both checking a shared, mislabelled "case documents" permission;
 * ExpenseCategoryController checking LIST_INVOICES instead of its own
 * expenses permission). A route responding 200 to a user who does NOT
 * hold that route's own permission is exactly the bug class this suite
 * exists to catch — and it would catch it silently reappearing in a
 * completely different module too, since every module is checked here
 * against every OTHER module's permission, not just its own.
 */
class PermissionMatrixTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    /**
     * @return array<string, array{method: string, url: string, permission: string}>
     */
    private function listRoutes(): array
    {
        return [
            'clients' => ['method' => 'get', 'url' => '/api/clients', 'permission' => 'list-clients'],
            'cases' => ['method' => 'get', 'url' => '/api/cases', 'permission' => 'list-cases'],
            'hearings' => ['method' => 'get', 'url' => '/api/hearings', 'permission' => 'list-hearings'],
            'expenses' => ['method' => 'get', 'url' => '/api/expenses', 'permission' => 'list-expenses'],
            'invoices' => ['method' => 'get', 'url' => '/api/invoices', 'permission' => 'list-invoice'],
            'courts' => ['method' => 'get', 'url' => '/api/courts', 'permission' => 'list-court'],
        ];
    }

    public function test_every_list_route_requires_exactly_its_own_permission(): void
    {
        $routes = $this->listRoutes();

        foreach ($routes as $moduleName => $route) {
            // A user with NO permissions at all must be rejected.
            $noPermsUser = $this->userWithPermissions([]);
            $response = $this->actingAs($noPermsUser)->json($route['method'], $route['url']);
            $this->assertEquals(
                403,
                $response->status(),
                "Expected 403 for {$moduleName} with no permissions, got {$response->status()}"
            );

            // A user with every OTHER module's permission, but not this
            // one, must still be rejected — this is precisely the check
            // that catches a controller accidentally validating against
            // the wrong module's permission constant.
            $otherPermissions = collect($routes)
                ->except($moduleName)
                ->pluck('permission')
                ->values()
                ->all();
            $wrongPermsUser = $this->userWithPermissions($otherPermissions);
            $response = $this->actingAs($wrongPermsUser)->json($route['method'], $route['url']);
            $this->assertEquals(
                403,
                $response->status(),
                "Expected 403 for {$moduleName} with every OTHER module's permissions, got {$response->status()}. ".
                'This means the route is not checking its own permission constant.'
            );

            // A user with exactly this module's permission must succeed.
            $correctPermsUser = $this->userWithPermissions([$route['permission']]);
            $response = $this->actingAs($correctPermsUser)->json($route['method'], $route['url']);
            $this->assertEquals(
                200,
                $response->status(),
                "Expected 200 for {$moduleName} with its own permission, got {$response->status()}"
            );
        }
    }

    public function test_hearings_and_cases_do_not_share_a_permission_constant(): void
    {
        // SEC-3's specific historical bug: HearingController and
        // CasesController both checked ModulePermissions::LIST_CASE_DOCUMENTS
        // (a mislabelled constant actually valued 'list-cases'). A user
        // with only 'list-hearings' must NOT be able to list cases, and
        // vice versa.
        $hearingsOnlyUser = $this->userWithPermissions(['list-hearings']);
        $this->actingAs($hearingsOnlyUser)->getJson('/api/cases')->assertStatus(403);

        $casesOnlyUser = $this->userWithPermissions(['list-cases']);
        $this->actingAs($casesOnlyUser)->getJson('/api/hearings')->assertStatus(403);
    }

    public function test_expense_categories_do_not_check_invoice_permission(): void
    {
        // SEC-3's specific historical bug: ExpenseCategoryController::index
        // checked ModulePermissions::LIST_INVOICES instead of an expenses
        // permission. A user with only invoice permissions must not be
        // able to list expense categories.
        $invoiceOnlyUser = $this->userWithPermissions(['list-invoice']);

        $this->actingAs($invoiceOnlyUser)
            ->getJson('/api/expense-categories')
            ->assertStatus(403);
    }

    public function test_create_update_delete_each_require_their_own_permission(): void
    {
        // Spot-check the write actions on one representative module
        // (cases) beyond just list, since SEC-3/SEC-11-class bugs
        // historically hit create/update endpoints with zero permission
        // check at all, not just wrong-constant checks on index().
        $case = Cases::factory()->create();

        $noPerms = $this->userWithPermissions([]);
        $this->actingAs($noPerms)->postJson('/api/cases', [])->assertStatus(403);
        $this->actingAs($noPerms)->putJson("/api/cases/{$case->id}", [])->assertStatus(403);
        $this->actingAs($noPerms)->deleteJson("/api/cases/{$case->id}")->assertStatus(403);

        $wrongPerms = $this->userWithPermissions(['list-clients', 'create-clients', 'update-clients', 'delete-clients']);
        $this->actingAs($wrongPerms)->postJson('/api/cases', [])->assertStatus(403);
        $this->actingAs($wrongPerms)->putJson("/api/cases/{$case->id}", [])->assertStatus(403);
        $this->actingAs($wrongPerms)->deleteJson("/api/cases/{$case->id}")->assertStatus(403);
    }
}
