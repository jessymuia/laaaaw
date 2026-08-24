/**
 * Reusable helpers for filling the client/case creation modals through
 * the real rendered form — several journeys create a client and/or case
 * as their first step, so this avoids duplicating brittle selector logic
 * across spec files. None of the target elements have data-testid or
 * label `for` associations in the actual markup, so these locate by
 * placeholder text and by the field-group's visible label text, matched
 * against the real templates in resources/js/src/views/menu/{clients,cases}.
 */

async function fillClientForm(page, { name, phone, address, advocateName }) {
  await page.locator('#clientName').fill(name);
  await page.locator('#phoneNumber').fill(phone);
  await page.locator('#address').fill(address);
  await page.locator('#advocate').selectOption({ label: advocateName });
}

async function submitClientForm(page) {
  await page.locator('#client_modal button.btn-submit, #client_modal button[type="submit"]').first().click();
}

async function fillCaseForm(page, { caseNumber, description, clientName, attorneyName, startDate, caseType, policeStation, courtName, opposingParty }) {
  await page.getByPlaceholder('Enter case number').fill(caseNumber);
  await page.getByPlaceholder('Brief description').fill(description);

  const clientGroup = page.locator('.form-group', { has: page.locator('.form-label', { hasText: 'Client' }) });
  await clientGroup.locator('select').selectOption({ label: clientName });

  const attorneyGroup = page.locator('.form-group', { has: page.locator('.form-label', { hasText: 'Attorney' }) });
  await attorneyGroup.locator('select').selectOption({ label: attorneyName });

  const startDateGroup = page.locator('.form-group', { has: page.locator('.form-label', { hasText: 'Start Date' }) });
  await startDateGroup.locator('input.app-date-picker').fill(startDate);

  await page.getByPlaceholder('e.g., Civil, Criminal').fill(caseType);
  await page.getByPlaceholder('Station name').fill(policeStation);

  const courtGroup = page.locator('.form-group', { has: page.locator('.form-label', { hasText: 'Court' }) });
  await courtGroup.locator('select').selectOption({ label: courtName });

  await page.getByPlaceholder('Opposing party name').fill(opposingParty);
}

module.exports = { fillClientForm, submitClientForm, fillCaseForm };
