import { test, expect } from '@playwright/test';
import { login, screenshot, expectCurrencyFormat } from './fixtures/test-helpers';

/**
 * QA Plan Section 11: Dashboard Financiero
 */

test.describe('Financial Dashboard', () => {
  test('KPIs render correctly @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financial-dashboard-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '08-dashboard-overview');

    await expect(page.locator('text=/Capital Total/i').first()).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('text=/Comisiones/i').first()).toBeVisible();
  });

  test('charts render without errors @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financial-dashboard-page');
    await page.waitForLoadState('networkidle');

    await page.waitForTimeout(2_000);

    await screenshot(page, '08-dashboard-charts');

    // Chart.js renders canvas elements
    const charts = page.locator('canvas');
    const chartCount = await charts.count();
    expect(chartCount).toBeGreaterThan(0);
  });

  test('period selector works @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financial-dashboard-page');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '08-dashboard-period-selector');

    // Period selector has options with "en curso" or "cerrado" text
    const selector = page.locator('select');
    await expect(selector.first()).toBeVisible({ timeout: 10_000 });
    const options = await selector.first().textContent() || '';
    expect(options).toMatch(/en curso|cerrado|ejecutado/i);
  });

  test('member breakdown table @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financial-dashboard-page');
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(1_000);

    await screenshot(page, '08-dashboard-member-breakdown');
  });

  test('currency format RD$ with 2 decimals @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin/financial-dashboard-page');
    await page.waitForLoadState('networkidle');

    const allText = await page.textContent('body') || '';
    const rdMatches = allText.match(/RD\$\s?[\d,]+\.?\d*/g) || [];

    for (const match of rdMatches) {
      expect(expectCurrencyFormat(match)).toBeTruthy();
    }

    await screenshot(page, '08-dashboard-currency-format');
  });
});
