import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 15: Usuarios y Roles
 */

test.describe('Users and Roles', () => {
  test('super_admin sees users list @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '11-users-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });

  test('operator cannot access users @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'operator');
    const response = await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    const url = page.url();
    const status = response?.status() || 200;
    const isDenied = status === 403 || status === 404 || !url.includes('/users');
    expect(isDenied).toBeTruthy();
  });
});
