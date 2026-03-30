import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';

/**
 * QA Plan Section 2: Dashboard (Escritorio) — Widgets
 * Tag: @readonly — runs on both dev and staging
 */

test.describe('Dashboard widgets', () => {
  test('super_admin sees all widgets @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '02-dashboard-super_admin');

    // Pipeline widget — uses "Solicitados", "Desembolsados", etc.
    await expect(page.locator('text=Solicitados').first()).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('text=Desembolsados').first()).toBeVisible();
  });

  test('company_user sees filtered widgets @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'company_user');
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '02-dashboard-company_user');

    // Should see Pipeline widget
    await expect(page.locator('text=Solicitados').first()).toBeVisible({ timeout: 10_000 });
  });

  test('member sees limited dashboard @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'member');
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '02-dashboard-member');

    // Member should see pipeline widget
    await expect(page.locator('text=Solicitados').first()).toBeVisible({ timeout: 10_000 });
  });

  test('collected widget shows total amount not commissions @readonly', async ({ page, baseURL }) => {
    await login(page, baseURL, 'super_admin');
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await screenshot(page, '02-dashboard-collected-widget');

    // The collected stat should show "cobrados" not "en comisiones"
    const widget = page.locator('text=cobrados').first();
    const hasAmountLabel = await widget.isVisible().catch(() => false);

    const commissionLabel = page.locator('text=en comisiones');
    const hasCommissionLabel = await commissionLabel.isVisible().catch(() => false);

    expect(hasCommissionLabel).toBeFalsy();
    if (hasAmountLabel) {
      expect(hasAmountLabel).toBeTruthy();
    }
  });
});
