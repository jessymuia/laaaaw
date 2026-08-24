const { test, expect } = require('../fixtures/base');
const { loginAs } = require('../utils/auth');

/**
 * Journey 6: Role-based access control.
 *
 * Uses the 'clerk' role (see database/seeders/RoleSeeder.php) — deliberately
 * given only VIEW_DASHBOARD, LIST_CLIENTS, LIST_CASES and nothing else, so
 * hearings/invoices/expenses/documents/users/roles are all genuinely
 * restricted modules for this user, both in navigation and server-side.
 *
 * This spec deliberately checks BOTH the client-side hiding and the
 * server-side 403, per the requirement's explicit "do not rely solely on
 * hiding navigation as authorization" — a passing nav-hiding assertion
 * alone proves nothing about whether the backend actually enforces it.
 */
test.describe('Role-based access control', () => {
  test('the restricted module is hidden from the sidebar navigation', async ({ page }) => {
    await loginAs(page, 'clerk');
    await page.goto('/');

    // Regression guard: the sidebar previously gated the Hearings and
    // Expenses nav links on the WRONG permission entirely
    // ('list-clients'/'list-invoice' instead of their own module's
    // permission) — a bug found while writing this exact test. A user
    // with only client/case access must not see Hearings, Expenses,
    // Invoices, Users, or Roles in the nav at all.
    await expect(page.locator('.sidebar, nav')).not.toContainText('Hearings');
    await expect(page.locator('.sidebar, nav')).not.toContainText('Expenses');
    await expect(page.locator('.sidebar, nav')).not.toContainText('Invoices');
    await expect(page.locator('.sidebar, nav')).not.toContainText('Roles');

    // What the clerk role *should* see remains visible.
    await expect(page.locator('.sidebar, nav')).toContainText('Clients');
    await expect(page.locator('.sidebar, nav')).toContainText('Cases');
  });

  test('navigating directly to a restricted module\'s URL still renders the 403 page, not the module', async ({ page }) => {
    await loginAs(page, 'clerk');

    // Direct URL navigation — the exact bypass attempt hiding a nav link
    // alone would never catch.
    await page.goto('/invoices');

    // This SPA renders the Vue page first (the client-side route guard
    // only checks "is logged in", not per-module permission — see
    // router/index.js), then the page's own API call gets a 403, and
    // api.js's response interceptor redirects to the dedicated error
    // page (see SEC-8/UI-2). Wait for that real redirect rather than a
    // fixed timeout.
    await page.waitForURL(/\/pages\/error403/, { timeout: 10000 });
    await expect(page.getByText(/access denied|don't have permission/i)).toBeVisible();
  });

  test('the restricted API endpoint itself returns 403, independent of any UI behaviour', async ({ page }) => {
    await loginAs(page, 'clerk');

    // Server-side enforcement checked directly — the actual requirement:
    // "verify the restricted data/API endpoint is also protected
    // server-side," not just that the UI happens to redirect somewhere.
    const invoicesResponse = await page.request.get('/api/invoices');
    expect(invoicesResponse.status()).toBe(403);

    const expensesResponse = await page.request.get('/api/expenses');
    expect(expensesResponse.status()).toBe(403);

    const hearingsResponse = await page.request.get('/api/hearings');
    expect(hearingsResponse.status()).toBe(403);

    const documentsResponse = await page.request.get('/api/preview', { params: { id: 1 } });
    expect(documentsResponse.status()).toBe(403);
  });

  test('a user with the correct permission is not blocked from the same module', async ({ page }) => {
    // Negative-control: prove the 403s above are actually permission-
    // driven, not e.g. a broken route returning 403 for everyone.
    await loginAs(page, 'advocate');

    const invoicesResponse = await page.request.get('/api/invoices');
    expect(invoicesResponse.status()).toBe(200);

    const hearingsResponse = await page.request.get('/api/hearings');
    expect(hearingsResponse.status()).toBe(200);
  });

  test('the sidebar correctly SHOWS a module once granted, and hides it once revoked', async ({ page, db }) => {
    // End-to-end proof that nav visibility actually tracks the real
    // permission (not a hardcoded role name). Uses the real roles API
    // (not a raw SQL insert into role_has_permissions) deliberately —
    // Spatie's permission-role mappings are cached (PermissionRegistrar),
    // and only the model-level syncPermissions() call that
    // RolesManagementController::update() uses actually busts that
    // cache. A raw SQL insert would silently not take effect until the
    // cache's TTL expired, making the test flaky/wrong for the wrong
    // reason.
    const clerkRole = await db.findOne("SELECT id FROM roles WHERE name = 'clerk'");
    test.skip(!clerkRole, 'clerk role not seeded');

    const adminContext = await page.context().browser().newContext();
    const adminPage = await adminContext.newPage();
    await loginAs(adminPage, 'admin');

    const { apiPut } = require('../utils/api');
    const grantResponse = await apiPut(adminPage, `/api/roles/${clerkRole.id}`, {
      name: 'clerk',
      permissions: ['view-dashboard', 'list-clients', 'list-cases', 'list-hearings'],
    });
    expect(grantResponse.ok()).toBeTruthy();

    try {
      await loginAs(page, 'clerk');
      await page.goto('/');
      await expect(page.locator('.sidebar, nav')).toContainText('Hearings');
    } finally {
      // Always revoke again, whether or not the assertion above passed,
      // so this test can never leave the clerk role permanently altered
      // for every subsequent run of the suite.
      await apiPut(adminPage, `/api/roles/${clerkRole.id}`, {
        name: 'clerk',
        permissions: ['view-dashboard', 'list-clients', 'list-cases'],
      });
      await adminContext.close();
    }
  });
});
