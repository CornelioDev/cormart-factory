import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Sections 3-4: Compañías y Clientes (CRUD)
 */

test.describe('Companies', () => {
  test('list companies @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/companies');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '03-companies-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });

  test('view company detail @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/companies');
    await page.waitForLoadState('networkidle');

    // Click first row link (edit action in Filament)
    const firstRow = page.locator('table tbody tr').first();
    await firstRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '03-company-detail');
  });

  test('create company form loads (dev only)', async ({ page, baseURL }) => {
    if (baseURL?.includes('ngrok')) test.skip();

    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/companies/create');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '03-company-create-form');

    // Verify form loads — Filament renders "Crear Compañía" heading
    await expect(page.locator('text=Crear Compañía')).toBeVisible({ timeout: 10_000 });
  });
});

test.describe('Clients', () => {
  test('list clients @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/clients');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '04-clients-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });
});
