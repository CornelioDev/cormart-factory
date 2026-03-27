import { test } from '@playwright/test';
import { login } from '../fixtures/test-helpers';

const roles = ['super_admin', 'operator', 'member', 'company_user'] as const;

for (const role of roles) {
  test(`authenticate as ${role}`, async ({ page, baseURL }) => {
    await login(page, baseURL, role);
    await page.context().storageState({ path: `e2e/auth/dev-${role}.json` });
  });
}
