import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 8-9: Miembros del Fondo y Estado de Cuenta
 */

test.describe('Fund Members', () => {
  test('list fund members as super_admin @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/fund-members');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '06-fund-members-list');

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10_000 });
  });

  test('view fund member detail @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/fund-members');
    await page.waitForLoadState('networkidle');

    const firstRow = page.locator('table tbody tr').first();
    await firstRow.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '06-fund-member-detail');
  });
});

test.describe('Member Account Page', () => {
  test('member sees Estado de Cuenta @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'member');

    // Click Estado de Cuenta in sidebar (it redirects to fund-members/{id})
    const link = page.locator('.fi-sidebar-nav a').filter({ hasText: 'Estado de Cuenta' });
    await link.click();
    await page.waitForLoadState('networkidle');

    await screenshot(page, '06-member-account');

    // Should see fund member detail page
    const url = page.url();
    expect(url).toMatch(/fund-members\/\d+/);
  });

  test('member cannot access other members @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'member');
    const response = await page.goto('/admin/fund-members');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '06-member-access-denied');

    const url = page.url();
    const status = response?.status() || 200;
    const isDenied = status === 403 || !url.includes('/fund-members') ||
      (await page.locator('text=/403|Forbidden|No autorizado/i').isVisible({ timeout: 3_000 }).catch(() => false));
    expect(isDenied).toBeTruthy();
  });
});
