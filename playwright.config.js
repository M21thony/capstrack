import { defineConfig, devices } from '@playwright/test';

// CapsTrack (Laravel) E2E tests. Points at the same dev server URL as
// APP_URL in .env - if `php artisan serve` is already running, Playwright
// reuses it (reuseExistingServer) instead of starting a second copy; if
// nothing's running, it starts one itself and tears it down after the run.
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: 'html',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'php artisan serve',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: true,
    timeout: 30 * 1000,
  },
});
