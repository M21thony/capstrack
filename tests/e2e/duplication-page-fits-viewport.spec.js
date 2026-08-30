import { test, expect } from '@playwright/test';

// The redesigned student/duplication page is meant to fit in one screen
// with nothing to scroll down to see - this locks that in so a future
// change to the summary strip, upload zone, or grid spacing gets caught
// if it quietly pushes the page taller again.
test('student/duplication fits in one viewport, no vertical scroll', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/__dev-login-student');
  await expect(page).toHaveURL(/\/student\/duplication/);

  const { scrollHeight, clientHeight } = await page.evaluate(() => ({
    scrollHeight: document.documentElement.scrollHeight,
    clientHeight: document.documentElement.clientHeight,
  }));

  console.log(`scrollHeight=${scrollHeight}, clientHeight=${clientHeight}, overflow=${scrollHeight - clientHeight}px`);
  await page.screenshot({ path: 'tests/e2e/__screenshots__/duplication-page.png', fullPage: true });

  expect(scrollHeight).toBeLessThanOrEqual(clientHeight);
});
