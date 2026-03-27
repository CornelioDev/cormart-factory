import { test as base, expect, Page } from '@playwright/test';
import { Role, getCredentials } from './credentials';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Login helper — restores session from stored auth state if available,
 * otherwise performs a full login via the UI.
 */
export async function login(page: Page, baseURL: string | undefined, role: Role): Promise<void> {
  const authFile = path.resolve(`e2e/auth/dev-${role}.json`);

  // Restore cookies from stored auth state (saved by auth/setup.ts)
  if (fs.existsSync(authFile)) {
    const state = JSON.parse(fs.readFileSync(authFile, 'utf-8'));
    if (state.cookies?.length) {
      await page.context().addCookies(state.cookies);
      await page.goto('/admin');
      await page.waitForLoadState('networkidle');

      if (!page.url().includes('/login')) {
        return;
      }
    }
  }

  // Fallback: full login via UI
  await page.goto('/admin/login');

  if (!page.url().includes('/login')) {
    return;
  }

  const creds = getCredentials(baseURL, role);

  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_000);

  const emailInput = page.getByLabel('Correo electrónico');
  const passwordInput = page.getByLabel('Contraseña');

  await emailInput.click();
  await emailInput.pressSequentially(creds.email, { delay: 30 });
  await passwordInput.click();
  await passwordInput.pressSequentially(creds.password, { delay: 30 });

  await passwordInput.press('Enter');

  try {
    await page.waitForURL(/\/admin(?!\/login)/, { timeout: 8_000 });
  } catch {
    await page.getByRole('button', { name: 'Entrar' }).click();
    await page.waitForURL(/\/admin(?!\/login)/, { timeout: 10_000 });
  }

  await page.waitForLoadState('networkidle');
}

/**
 * Verify currency format: RD$ with 2 decimal places.
 */
export function expectCurrencyFormat(text: string): boolean {
  return /RD\$\s?[\d,]+\.\d{2}/.test(text);
}

/**
 * Take a labeled screenshot for QA evidence.
 */
export async function screenshot(page: Page, name: string): Promise<void> {
  await page.screenshot({ path: `e2e/screenshots/${name}.png`, fullPage: true });
}

export { base as test, expect };
