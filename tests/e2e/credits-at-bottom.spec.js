import { expect, test } from '@playwright/test';

/**
 * The developers' credit sits at the very bottom of every portal page, the
 * way it always has on the student portal: at the end of a long page, and
 * held down at the foot of the screen on a short one rather than left
 * halfway up under the last card.
 *
 * partials/mobile-tabbar places it. What went wrong before was the expert
 * portal's pages (and a few others), whose page container grows with its
 * content inside a box that is the one held to the screen - measured in
 * the container, a short page had "no room left".
 */

const PAGES = {
  student: ['dashboard', 'progress', 'resources'],
  adviser: ['dashboard', 'docreview', 'grading', 'schedule'],
  capsa: ['dashboard', 'announcements', 'financial'],
  admin: ['dashboard', 'settings'],
};

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'phone', width: 390, height: 844 },
];

const signIn = async (page, role, at) => {
  const url = role === 'student' ? `/__dev-login-student/${at}` : `/__dev-login-expert/${role}/${at}`;
  const response = await page.goto(url);
  test.skip(response.status() === 404, `there is no ${role} account in this database to sign in as`);
  await expect(page, `signed in as ${role}`).not.toHaveURL(/\/login/);
};

for (const vp of VIEWPORTS) {
  for (const [role, pages] of Object.entries(PAGES)) {
    for (const at of pages) {
      test(`${role} ${at} (${vp.name}): the credits are the last thing on the page, at the bottom`, async ({ page }) => {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await signIn(page, role, at);

        const credits = page.locator('.legal-credits');
        await expect(credits).toHaveCount(1);
        await expect(credits).toContainText('System Developers');

        // Let the page settle (the skeleton lifting, lists loading), then go
        // to the end of whatever scrolls, as a reader would.
        await page.waitForTimeout(1200);
        const gap = await page.evaluate(() => {
          const el = document.querySelector('.legal-credits');
          for (let box = el.parentElement; box; box = box.parentElement) {
            box.scrollTop = box.scrollHeight;
          }
          // Measured to the top of the phone tab bar, floating or not - with
          // the student's floating one brought back if scrolling hid it.
          const bar = document.querySelector('.mtab');
          bar?.classList.remove('is-away');
          const barTop = bar && getComputedStyle(bar).display !== 'none' ? bar.getBoundingClientRect().top : innerHeight;
          return Math.round(barTop - el.getBoundingClientRect().bottom);
        });

        // Only the page's own bottom padding (and, over a floating bar, a little
        // clearance) may sit between it and the bar.
        expect(gap, `the credits end ${gap}px above the bottom of the screen`).toBeLessThanOrEqual(30);
        expect(gap, `the credits run ${-gap}px past the bottom of the screen`).toBeGreaterThanOrEqual(-2);
      });
    }
  }
}
