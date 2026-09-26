import { expect, test } from '@playwright/test';

/**
 * Moving around the portals: a page cross-fades into the next, the next
 * page is fetched as its link is pointed at, and a page holds still as it
 * loads rather than jumping as a skeleton leaves or a grid is drawn.
 */

const shifts = () => {
  window.__shift = 0;
  new PerformanceObserver((list) => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.__shift += e.value; })
    .observe({ type: 'layout-shift', buffered: true });
};

test('a sidebar link is fetched on hover and its page cross-fades in', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  const response = await page.goto('/__dev-login-student/dashboard');
  test.skip(response.status() === 404, 'there is no student in this database');
  await page.evaluate(() => document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove()));

  const prefetched = [];
  page.on('request', (r) => { if ((r.headers()['sec-purpose'] || '').includes('prefetch')) prefetched.push(new URL(r.url()).pathname); });
  const link = page.locator('.sidebar a.nav-item[href*="/student/progress"]').first();
  await link.hover();
  await expect.poll(() => prefetched).toContain('/student/progress');

  await page.evaluate(() => { window.__before = true; });
  await Promise.all([page.waitForURL(/progress/), link.click()]);
  expect(await page.evaluate(() => !!document.startViewTransition)).toBe(true);
});

for (const [role, path] of [['adviser', 'instructor/dashboard'], ['adviser', 'instructor/schedule'], ['capsa', 'capsa/team-management']]) {
  for (const [width, height] of [[1440, 900], [390, 844]]) {
    test(`${path} at ${width}px holds still as it loads`, async ({ browser }) => {
      const context = await browser.newContext({ viewport: { width, height }, isMobile: width < 800, hasTouch: width < 800 });
      await context.addInitScript(shifts);
      const page = await context.newPage();
      const response = await page.goto(`/__dev-login-expert/${role}/dashboard`);
      test.skip(response.status() === 404, `there is no ${role} account in this database`);
      await page.goto('/' + path);
      await page.waitForTimeout(1500);
      expect(await page.evaluate(() => window.__shift), 'layout shift while loading').toBeLessThan(0.1);
      await context.close();
    });
  }
}
