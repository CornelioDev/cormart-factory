import { test, expect } from '@playwright/test';
import { login, screenshot, expectCurrencyFormat } from './fixtures/test-helpers';

/**
 * QA Plan Section 16: Formato y UX general
 */

test.describe('UX and Formatting', () => {
  test('currency format across financings @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    const allText = await page.textContent('body') || '';
    const rdMatches = allText.match(/RD\$\s?[\d,]+\.?\d*/g) || [];

    for (const match of rdMatches) {
      expect(expectCurrencyFormat(match)).toBeTruthy();
    }

    await screenshot(page, '12-format-financings');
  });

  test('currency format across transactions @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/transactions');
    await page.waitForLoadState('networkidle');

    const allText = await page.textContent('body') || '';
    const rdMatches = allText.match(/RD\$\s?[\d,]+\.?\d*/g) || [];

    for (const match of rdMatches) {
      expect(expectCurrencyFormat(match)).toBeTruthy();
    }

    await screenshot(page, '12-format-transactions');
  });

  test('no 500 errors on main pages @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');

    const pages = [
      '/admin',
      '/admin/financings',
      '/admin/transactions',
      '/admin/companies',
      '/admin/clients',
      '/admin/fund-members',
      '/admin/monthly-closings',
      '/admin/financial-dashboard',
      '/admin/cuentas-por-cobrar',
      '/admin/cuentas-por-pagar',
      '/admin/parametros',
      '/admin/users',
    ];

    const errors: string[] = [];

    for (const pagePath of pages) {
      const response = await page.goto(pagePath);
      if (response && response.status() >= 500) {
        errors.push(`${pagePath} returned ${response.status()}`);
      }
      await screenshot(page, `12-page-${pagePath.replace(/\//g, '_')}`);
    }

    expect(errors).toEqual([]);
  });

  test('UI labels in Spanish @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financings');
    await page.waitForLoadState('networkidle');

    // Check that common labels are in Spanish
    const bodyText = await page.textContent('body') || '';

    // Should have Spanish labels, not English
    const hasSpanish = /Financiamientos|Compañía|Estado|Monto|Fecha/.test(bodyText);
    expect(hasSpanish).toBeTruthy();

    await screenshot(page, '12-labels-spanish');
  });
});
