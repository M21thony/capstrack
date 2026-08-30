import { test, expect } from '@playwright/test';

// First real test through this setup - just confirms the dev server is
// reachable and the login page renders, before writing anything CapsTrack-
// feature-specific on top of it.
test('login page loads', async ({ page }) => {
  await page.goto('/login');
  await expect(page).toHaveURL(/\/login/);
  await expect(page.locator('form')).toBeVisible();
});
