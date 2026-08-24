// @ts-check
const { defineConfig, devices } = require('@playwright/test');
require('dotenv').config();

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8000';

/**
 * §3.2: E2E suite configuration.
 *
 * - fullyParallel: false. Several specs share seeded fixture rows (the
 *   E2E test users, and cases/invoices created by earlier specs) and
 *   assert exact counts/positions in places — running spec FILES in
 *   parallel risks cross-test interference. Tests within a single file
 *   still run serially by describe-block default, which is what we want
 *   for the create → verify → cleanup pattern used throughout.
 * - retries: 0 locally, 1 in CI. A flaky-looking failure should be
 *   visible locally; in CI a single infra hiccup (e.g. a slow DB
 *   container on first boot) shouldn't fail the whole run, but repeated
 *   failures still surface as real failures rather than being masked.
 * - No fixed sleeps/waits are used anywhere in the suite — every wait is
 *   for a specific element, network response, or URL state, per the
 *   "deterministic, no arbitrary timeouts" requirement.
 */
module.exports = defineConfig({
  testDir: './specs',
  timeout: 45_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { open: 'never' }],
  ],
  use: {
    baseURL: BASE_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
