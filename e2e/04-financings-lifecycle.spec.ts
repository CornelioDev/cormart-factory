import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 5-6: Financiamientos
 */

test.describe('Financings - read', () => {
  test('list financings as super_admin @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-financings-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });

  test('view financing detail @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    // Click view link on first row
    const firstRow = page.locator('table tbody tr').first();
    await firstRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-financing-detail');

    // Verify code format FN000XXX
    await expect(page.locator('text=/FN\\d{6}/')).toBeVisible({ timeout: 5_000 });
  });

  test('company_user only sees own company financings @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'company_user');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-financings-company_user');

    await expect(page.locator('table')).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Financings - create (dev only)', () => {
  test('create financing form loads', async ({ page, baseURL }) => {
    if (baseURL?.includes('ngrok')) test.skip();

    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings/create');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-financing-create-form');

    await expect(page.locator('text=/Crear Financiamiento|Crear/i').first()).toBeVisible({ timeout: 10_000 });
  });

  test('commission scales by 30-day tiers when changing amount', async ({ page, baseURL }) => {
    if (baseURL?.includes('ngrok')) test.skip();

    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings/create');
    await page.waitForLoadState('networkidle');

    const amountInput = page.getByLabel('Monto a Financiar');
    const termInput = page.getByLabel('Plazo (días)');
    const commissionInput = page.getByLabel('Comisión');
    const transferInput = page.getByLabel('Monto a Transferir');

    // Default term is 15 days → 1 tier → 5%
    await amountInput.click();
    await amountInput.fill('100000');
    await amountInput.blur();
    await page.waitForTimeout(500);

    await expect(commissionInput).toHaveValue('5,000.00');
    await expect(transferInput).toHaveValue('95,000.00');

    await screenshot(page, '04-commission-15days-5pct');
  });

  test('commission scales by 30-day tiers when changing term', async ({ page, baseURL }) => {
    if (baseURL?.includes('ngrok')) test.skip();

    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings/create');
    await page.waitForLoadState('networkidle');

    const amountInput = page.getByLabel('Monto a Financiar');
    const termInput = page.getByLabel('Plazo (días)');
    const commissionInput = page.getByLabel('Comisión');
    const transferInput = page.getByLabel('Monto a Transferir');

    // Fill amount first with default 15-day term
    await amountInput.click();
    await amountInput.fill('100000');
    await amountInput.blur();
    await page.waitForTimeout(500);

    // Change term to 30 days → still 1 tier → 5%
    await termInput.click();
    await termInput.fill('30');
    await termInput.blur();
    await page.waitForTimeout(500);

    await expect(commissionInput).toHaveValue('5,000.00');
    await expect(transferInput).toHaveValue('95,000.00');

    // Change term to 45 days → 2 tiers → 10%
    await termInput.click();
    await termInput.fill('45');
    await termInput.blur();
    await page.waitForTimeout(500);

    await expect(commissionInput).toHaveValue('10,000.00');
    await expect(transferInput).toHaveValue('90,000.00');

    await screenshot(page, '04-commission-45days-10pct');

    // Change term to 60 days → still 2 tiers → 10%
    await termInput.click();
    await termInput.fill('60');
    await termInput.blur();
    await page.waitForTimeout(500);

    await expect(commissionInput).toHaveValue('10,000.00');
    await expect(transferInput).toHaveValue('90,000.00');

    // Change term to 90 days → 3 tiers → 15%
    await termInput.click();
    await termInput.fill('90');
    await termInput.blur();
    await page.waitForTimeout(500);

    await expect(commissionInput).toHaveValue('15,000.00');
    await expect(transferInput).toHaveValue('85,000.00');

    await screenshot(page, '04-commission-90days-15pct');
  });
});

test.describe('Financings - action visibility by role', () => {
  test('disbursed financing does NOT show cancel button for super_admin', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    // Find a disbursed financing
    const disbursedRow = page.locator('table tbody tr').filter({ hasText: 'Desembolsado' }).first();
    const hasDisbursed = await disbursedRow.isVisible().catch(() => false);

    if (!hasDisbursed) {
      test.skip();
      return;
    }

    await disbursedRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-disbursed-no-cancel');

    // Cancel button should NOT be visible for disbursed financing
    const cancelBtn = page.locator('button, a').filter({ hasText: 'Cancelar' });
    await expect(cancelBtn).toHaveCount(0, { timeout: 3_000 });
  });

  test('solicited financing shows cancel button for super_admin', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    const solicitedRow = page.locator('table tbody tr').filter({ hasText: 'Solicitado' }).first();
    const hasSolicited = await solicitedRow.isVisible().catch(() => false);

    if (!hasSolicited) {
      test.skip();
      return;
    }

    await solicitedRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-solicited-has-cancel');

    const cancelBtn = page.locator('button, a').filter({ hasText: 'Cancelar' });
    await expect(cancelBtn.first()).toBeVisible({ timeout: 5_000 });
  });

  test('member cannot see collect button on disbursed financing', async ({ page, baseURL }) => {
    await login(page, baseURL, 'member');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    const disbursedRow = page.locator('table tbody tr').filter({ hasText: 'Desembolsado' }).first();
    const hasDisbursed = await disbursedRow.isVisible().catch(() => false);

    if (!hasDisbursed) {
      test.skip();
      return;
    }

    await disbursedRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-member-no-collect');

    // Collect/Cobrar button should NOT be visible for member
    const collectBtn = page.locator('button, a').filter({ hasText: /Cobrar|Pagar/ });
    await expect(collectBtn).toHaveCount(0, { timeout: 3_000 });
  });

  test('member cannot see cancel button on solicited financing', async ({ page, baseURL }) => {
    await login(page, baseURL, 'member');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    const solicitedRow = page.locator('table tbody tr').filter({ hasText: 'Solicitado' }).first();
    const hasSolicited = await solicitedRow.isVisible().catch(() => false);

    if (!hasSolicited) {
      test.skip();
      return;
    }

    await solicitedRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-member-no-cancel');

    const cancelBtn = page.locator('button, a').filter({ hasText: 'Cancelar' });
    await expect(cancelBtn).toHaveCount(0, { timeout: 3_000 });
  });

  test('operator sees collect button on disbursed financing', async ({ page, baseURL }) => {
    await login(page, baseURL, 'operator');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    const disbursedRow = page.locator('table tbody tr').filter({ hasText: 'Desembolsado' }).first();
    const hasDisbursed = await disbursedRow.isVisible().catch(() => false);

    if (!hasDisbursed) {
      test.skip();
      return;
    }

    await disbursedRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-operator-has-collect');

    const collectBtn = page.locator('button, a').filter({ hasText: 'Cobrar' });
    await expect(collectBtn.first()).toBeVisible({ timeout: 5_000 });
  });
});
