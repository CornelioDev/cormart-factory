import { defineConfig, devices } from '@playwright/test';

const DEV_URL = 'http://localhost:8000';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 1,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],
  timeout: 30_000,
  expect: { timeout: 10_000 },

  use: {
    headless: false,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    locale: 'es-DO',
    ...devices['Desktop Chrome'],
    baseURL: DEV_URL,
  },

  projects: [
    {
      name: 'auth',
      testDir: './e2e/auth',
      testMatch: 'setup.ts',
    },
    {
      name: 'dev',
      dependencies: ['auth'],
    },
  ],
});
