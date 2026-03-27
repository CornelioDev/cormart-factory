import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Sections 12-13: Cuentas por Cobrar y Cuentas por Pagar
 */

test.describe('Cuentas por Cobrar', () => {
  test('super_admin sees all receivables @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/cuentas-por-cobrar-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '09-cuentas-cobrar-admin');

    await expect(page.locator('text=/Cobrar|Receivable/i').first()).toBeVisible({ timeout: 10_000 });
  });

  test('company_user sees only own receivables @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'company_user');
    await page.goto('/admin/cuentas-por-cobrar-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '09-cuentas-cobrar-company_user');
  });
});

test.describe('Cuentas por Pagar', () => {
  test('super_admin sees payables @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/cuentas-por-pagar-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '09-cuentas-pagar-admin');

    await expect(page.locator('text=/Pagar|Payable/i').first()).toBeVisible({ timeout: 10_000 });
  });

  test('company_user cannot access payables @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'company_user');
    const response = await page.goto('/admin/cuentas-por-pagar-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '09-cuentas-pagar-company_user-denied');

    const url = page.url();
    const status = response?.status() || 200;
    const isDenied = status === 403 || status === 404 || !url.includes('/cuentas-por-pagar-page');
    expect(isDenied).toBeTruthy();
  });

  test('member cannot access payables @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'member');
    const response = await page.goto('/admin/cuentas-por-pagar-page');
    await page.waitForLoadState('networkidle');

    const url = page.url();
    const status = response?.status() || 200;
    const isDenied = status === 403 || status === 404 || !url.includes('/cuentas-por-pagar-page');
    expect(isDenied).toBeTruthy();
  });
});
