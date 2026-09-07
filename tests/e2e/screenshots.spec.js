import { test } from '@playwright/test';

/**
 * Not an assertion - a look.
 *
 * Every other spec says whether something is true. This one puts the pages
 * on screen so a person can judge for themselves, which is the half a test
 * suite cannot do.
 */
const PAGES = ['duplication', 'experts', 'team', 'review', 'submission'];

for (const name of PAGES) {
  test(`look at student/${name}`, async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`/__dev-login-student/${name}`);
    await page.waitForLoadState('networkidle');

    await testInfo.attach(`student-${name}`, {
      body: await page.screenshot({ fullPage: false }),
      contentType: 'image/png',
    });
  });
}

test('look at the original PDF panel', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/__dev-login-student/experts');
  await page.waitForLoadState('networkidle');

  if (await page.locator('#original-pdf-modal').count() === 0) {
    test.skip(true, 'no approved pre-proposal with a file');
  }

  await page.evaluate(() => window.openOriginalPdf());
  await page.waitForTimeout(1200);

  await testInfo.attach('original-pdf-panel', {
    body: await page.screenshot(),
    contentType: 'image/png',
  });
});
