const { test, expect } = require('../fixtures/base');
const { loginAs, logout } = require('../utils/auth');

/**
 * Journey 1: Authentication -> Dashboard.
 *
 * Note on "verify the user cannot see data belonging to another firm":
 * this application has no multi-tenant/firm concept anywhere in its data
 * model (no `firm_id` column on any table, no Firm model, no tenant
 * scoping middleware) — it is a single law firm's internal system, by
 * design, per every other part of this migration plan. There is no
 * cross-firm isolation to test because there is no multi-firm data
 * model to isolate. The closest real, already-implemented equivalent is
 * per-user data scoping within the one firm: DashboardController scopes
 * a non-admin user's tasks/invoices/time-entry totals to their own
 * records, while an admin sees firm-wide totals (see
 * app/Http/Controllers/DashboardController.php). That is what this file
 * actually verifies below, and it is stated plainly rather than
 * fabricating a "firm" boundary that doesn't exist in the codebase.
 */
test.describe('Authentication -> Dashboard', () => {
  // @smoke — the minimal "is the app actually up and authenticating at
  // all" check run against real staging after every deploy (see
  // .github/workflows/deploy.yml's smoke-staging job). Deliberately just
  // this one test, not the whole file: a staging smoke gate should be
  // fast and only block deploys on fundamental breakage, not re-run the
  // full functional suite a second time against a live environment.
  test('@smoke logs in with valid credentials and reaches the dashboard', async ({ page }) => {
    await loginAs(page, 'admin');

    // Successful authentication: the app landed away from /login and the
    // session cookie is present (Sanctum SPA auth, SEC-8) — checked via a
    // real subsequent authenticated request rather than just trusting the
    // URL changed.
    await expect(page).toHaveURL((url) => !url.pathname.startsWith('/login'));

    const meResponse = await page.request.get('/api/dashboard');
    expect(meResponse.ok()).toBeTruthy();

    // Dashboard renders with its real section headings, not a blank or
    // error state.
    await expect(page.getByText('Upcoming Hearings')).toBeVisible();
    await expect(page.getByText('Tasks Due')).toBeVisible();
    await expect(page.getByText('Recent Case Activity')).toBeVisible();
  });

  test('rejects invalid credentials and stays on the login page', async ({ page }) => {
    await page.goto('/login');
    await page.locator('input[placeholder="Username"]').fill('e2e-admin@lawfirm.test');
    await page.locator('input[placeholder="Password"]').fill('definitely-the-wrong-password');
    await page.getByRole('button', { name: 'Log In' }).click();

    // Regression guard for SEC-5: this previously returned HTTP 200 with
    // a "400" string in the body instead of a real 401 — the login page
    // must show an error and the user must remain unauthenticated.
    await expect(page).toHaveURL(/\/login/);

    const stillProtected = await page.request.get('/api/dashboard');
    expect(stillProtected.status()).toBe(401);
  });

  test('dashboard KPIs match the actual persisted counts', async ({ page, db }) => {
    await loginAs(page, 'admin');
    await page.goto('/');

    const [{ total: expectedClients }] = await db.query(
      'SELECT COUNT(*) as total FROM clients WHERE deleted_at IS NULL'
    );
    const [{ total: expectedOpenCases }] = await db.query(
      "SELECT COUNT(*) as total FROM cases WHERE deleted_at IS NULL AND lifecycle_status = 'open'"
    );

    const clientsCard = page.locator('.stat-card', { has: page.locator('.stat-label', { hasText: 'Clients' }) });
    const openCasesCard = page.locator('.stat-card', { has: page.locator('.stat-label', { hasText: 'Open Cases' }) });

    await expect(clientsCard.locator('.stat-value')).toHaveText(String(expectedClients));
    await expect(openCasesCard.locator('.stat-value')).toHaveText(String(expectedOpenCases));
  });

  test('a non-admin user sees only their own scoped data, not the whole firm\'s', async ({ page, db }) => {
    // Substitutes for "another firm's data" per the note above: verifies
    // the real per-user scoping DashboardController already implements.
    const advocateUser = await db.getUserByEmail('e2e-advocate@lawfirm.test');
    const [{ total: theirTaskCount }] = await db.query(
      "SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND deleted_at IS NULL AND task_status != 'completed'",
      [advocateUser.id]
    );
    const [{ total: firmWideTaskCount }] = await db.query(
      "SELECT COUNT(*) as total FROM tasks WHERE deleted_at IS NULL AND task_status != 'completed'"
    );

    await loginAs(page, 'advocate');
    const response = await page.request.get('/api/dashboard');
    const body = await response.json();

    expect(body.data.tasks_due.length).toBeLessThanOrEqual(theirTaskCount);

    // If the firm has more open tasks assigned to *other* people than to
    // this advocate, the advocate's dashboard must not include them.
    if (firmWideTaskCount > theirTaskCount) {
      expect(body.data.tasks_due.length).toBeLessThan(firmWideTaskCount);
    }
  });

  test('logout actually ends the session (SEC-4 regression guard)', async ({ page }) => {
    await loginAs(page, 'admin');
    await logout(page);

    await expect(page).toHaveURL(/\/login/);

    const afterLogout = await page.request.get('/api/dashboard');
    expect(afterLogout.status()).toBe(401);
  });
});
