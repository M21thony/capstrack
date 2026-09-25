import { expect, test } from '@playwright/test';

/**
 * A new notification pops up as a slim banner at the top of a phone's
 * screen - clear of the tab bar and small enough not to hide the page - and
 * in the bottom corner, as before, on a computer.
 */

const popTwo = async (page) => {
  const feed = await page.evaluate(() => document.getElementById('nt-stack')?.dataset.feed);
  test.skip(!feed, 'no notification pop-ups on this page');
  const now = new Date().toISOString();
  const id = Date.now();
  await page.route(`${feed}*`, (route) => route.fulfill({ json: { notifications: [
    { id, type: 'feedback', title: 'New feedback on Chapter 2', message: 'Your adviser left 3 comments on the Review of Related Literature section of your manuscript.', time: 'just now', created_at: now, link: '#', is_read: false },
    { id: id + 1, type: 'announcement', title: 'Defense schedule posted', message: 'Final defense schedules for BSIT-4A are now available.', time: 'just now', created_at: now, link: '#', is_read: false },
  ] } }));
  // The pop-ups check the feed when the page comes back into view.
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await expect(page.locator('.nt')).toHaveCount(2);
  await page.evaluate(() => document.querySelectorAll('.nt-bar').forEach((bar) => { bar.style.animationPlayState = 'paused'; }));
  await page.waitForTimeout(600);
  return page.evaluate(() => [...document.querySelectorAll('.nt')].map((el) => el.getBoundingClientRect().toJSON()));
};

for (const [portal, signIn] of [['student', '/__dev-login-student/dashboard'], ['admin', '/__dev-login-expert/admin/dashboard']]) {
  for (const [width, height] of [[320, 568], [390, 844]]) {
    test(`${portal} on a ${width}x${height} phone: slim banners at the top`, async ({ browser }) => {
      const context = await browser.newContext({ viewport: { width, height }, isMobile: true, hasTouch: true });
      const page = await context.newPage();
      const response = await page.goto(signIn, { waitUntil: 'load' });
      test.skip(response.status() === 404, `there is no ${portal} account in this database`);

      const boxes = await popTwo(page);
      for (const box of boxes) {
        expect(box.height, 'one slim banner').toBeLessThanOrEqual(64);
        expect(box.top, 'at the top of the screen').toBeLessThan(height / 3);
        expect(box.left).toBeGreaterThanOrEqual(0);
        expect(box.right).toBeLessThanOrEqual(width);
      }
      // The newest on top.
      expect(boxes[1].top).toBeLessThan(boxes[0].top);
      await expect(page.locator('.nt .nt-title').first()).toBeVisible();
      await expect(page.locator('.nt .nt-foot').first()).toBeHidden();
      await context.close();
    });
  }
}

test('on a computer they stay in the bottom corner', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  const response = await page.goto('/__dev-login-student/dashboard', { waitUntil: 'load' });
  test.skip(response.status() === 404, 'there is no student in this database');

  const boxes = await popTwo(page);
  for (const box of boxes) {
    expect(box.top).toBeGreaterThan(800 / 2);
    expect(box.right).toBeGreaterThan(1280 - 40);
  }
});
