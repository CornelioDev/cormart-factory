import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 7: Transacciones
 */

test.describe('Transactions - read', () => {
  test('list transactions as super_admin @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/transactions');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '05-transactions-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });

  test('filter transactions by type @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/transactions');
    await page.waitForLoadState('networkidle');

    const filterButton = page.locator('button').filter({ hasText: /filtro|filter/i });
    if (await filterButton.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await filterButton.click();
      await screenshot(page, '05-transactions-filters');
    }
  });

  test('company_user only sees collections @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'company_user');
    await page.goto('/admin/transactions');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '05-transactions-company_user');

    await expect(page.locator('table')).toBeVisible({ timeout: 10_000 });

    // Verify no disbursement or expense rows
    const disbursementCells = page.locator('table tbody').locator('text=Desembolso');
    const expenseCells = page.locator('table tbody').locator('text=Gasto');
    await expect(disbursementCells).toHaveCount(0, { timeout: 3_000 });
    await expect(expenseCells).toHaveCount(0, { timeout: 3_000 });
  });
});

test.describe('Transactions - write (dev only)', () => {
  test('create transaction form loads', async ({ page, baseURL }) => {
    if (baseURL?.includes('ngrok')) test.skip();

    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/transactions/create');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '05-transaction-create-form');

    await expect(page.locator('text=/Crear Transacción|Crear/i').first()).toBeVisible({ timeout: 10_000 });
  });
});
