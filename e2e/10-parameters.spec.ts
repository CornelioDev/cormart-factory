import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 14: Parámetros del Sistema
 */

test.describe('Parameters', () => {
  test('super_admin sees parameters page @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/parametros-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '10-parametros-admin');

    // Should show parameter fields
    await expect(page.locator('text=/commission|comisión|Parámetro/i').first()).toBeVisible({ timeout: 10_000 });
  });

  test('operator cannot access parameters @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'operator');
    const response = await page.goto('/admin/parametros-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '10-parametros-operator-denied');

    const url = page.url();
    const status = response?.status() || 200;
    const isDenied = status === 403 || status === 404 || !url.includes('/parametros-page');
    expect(isDenied).toBeTruthy();
  });
});
