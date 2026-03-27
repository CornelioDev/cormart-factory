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
});
