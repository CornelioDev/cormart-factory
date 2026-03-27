import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 10: Cierres Mensuales
 */

test.describe('Monthly Closings', () => {
  test('list closings as super_admin @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/monthly-closings');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '07-closings-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });

  test('view closing detail with distributions @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/monthly-closings');
    await page.waitForLoadState('networkidle');

    const firstRow = page.locator('table tbody tr').first();
    await firstRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '07-closing-detail');

    await expect(page.locator('text=/Distribuc|Miembro/').first()).toBeVisible({ timeout: 5_000 });
  });

  test('closing execution page loads @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/monthly-closing-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '07-closing-execution-page');

    await expect(page.locator('text=/Cierre Mensual|Período/').first()).toBeVisible({ timeout: 5_000 });
  });

  test('operator cannot access closing execution page @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'operator');
    const response = await page.goto('/admin/monthly-closing-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '07-closing-operator-denied');

    const url = page.url();
    const status = response?.status() || 200;
    const isDenied = status === 403 || status === 404 || !url.includes('/monthly-closing-page');
    expect(isDenied).toBeTruthy();
  });
});
