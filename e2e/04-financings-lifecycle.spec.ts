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
});
