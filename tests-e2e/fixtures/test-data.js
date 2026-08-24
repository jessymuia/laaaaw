const { apiPost, uniqueSuffix } = require('../utils/api');

/**
 * Prerequisite fixture creation via the real API (see utils/api.js's
 * docblock for why this is not a mock: it's the same endpoint a user's
 * browser calls, authenticated the same way). Used where a journey's
 * setup needs data that already exists so the spec can focus on the
 * actual behaviour under test, per each journey's own description.
 */

async function createClient(page, overrides = {}) {
  const suffix = uniqueSuffix();
  const advocatesRes = await page.request.get('/api/advocates');
  const advocates = (await advocatesRes.json()).data;
  if (!advocates.length) throw new Error('No advocate users found — cannot create a test client without one.');

  const payload = {
    name: `E2E Client ${suffix}`,
    phone_number: `07${suffix}`.slice(0, 12),
    address: '123 Test Street, Nairobi',
    advocate: advocates[0].id,
    ...overrides,
  };

  const response = await apiPost(page, '/api/clients', payload);
  if (!response.ok()) {
    throw new Error(`Failed to create test client: ${response.status()} ${await response.text()}`);
  }
  const body = await response.json();
  return { ...body.data, _requestPayload: payload };
}

async function createCase(page, client, overrides = {}) {
  const suffix = uniqueSuffix();
  const courtsRes = await page.request.get('/api/courts');
  const courts = (await courtsRes.json()).data;
  if (!courts.length) throw new Error('No courts found — cannot create a test case without one.');

  const advocatesRes = await page.request.get('/api/advocates');
  const advocates = (await advocatesRes.json()).data;

  const today = new Date();
  const startDate = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;

  const payload = {
    case_number: `E2E-CASE-${suffix}`,
    description: 'E2E test case description',
    client_id: client.id,
    assigned_to: advocates[0].id,
    start_date: startDate,
    case_type: 'civil',
    police_station: 'Test Station',
    court_id: courts[0].id,
    opposing_party: 'E2E Opposing Party',
    ...overrides,
  };

  const response = await apiPost(page, '/api/cases', payload);
  if (!response.ok()) {
    throw new Error(`Failed to create test case: ${response.status()} ${await response.text()}`);
  }
  const body = await response.json();
  return { ...body.data, _requestPayload: payload };
}

module.exports = { createClient, createCase };
