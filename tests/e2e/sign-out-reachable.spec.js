import { expect, test } from '@playwright/test';

/**
 * Sign Out, at the very bottom of every portal's sidebar, can be seen and
 * tapped on a phone.
 *
 * On a phone 100vh is the screen with the browser's bars hidden (Safari's
 * toolbar, the address bar in Chrome and Brave), so a 100vh sidebar ran
 * under them while they showed and Sign Out was out of sight. The sidebar is
 * now 100dvh - the part actually showing - and a browser too old for dvh is
 * given the visible height by script.
 */

const PORTALS = [
  ['student', '/__dev-login-student/dashboard'],
  ['adviser', '/__dev-login-expert/adviser/dashboard'],
  ['capsa', '/__dev-login-expert/capsa/dashboard'],
  ['admin', '/__dev-login-expert/admin/dashboard'],
];

const openMenu = async (page) => {
  await page.evaluate(() => document.querySelectorAll('#portal-tour, .gt').forEach((el) => el.remove()));
  const menu = page.locator('button[aria-label="Open the full menu"]');
  if (await menu.isVisible()) {
    await menu.tap();
  } else {
    await page.evaluate(() => document.querySelector('.hamburger-btn')?.click());
  }
  await page.waitForTimeout(500);
};

for (const [width, height] of [[320, 480], [390, 844]]) {
  for (const [portal, signIn] of PORTALS) {
    test(`${portal} at ${width}x${height}: Sign Out can be seen and tapped`, async ({ browser }) => {
      const context = await browser.newContext({ viewport: { width, height }, isMobile: true, hasTouch: true });
      const page = await context.newPage();
      const response = await page.goto(signIn);
      test.skip(response.status() === 404, `there is no ${portal} account in this database`);
      await openMenu(page);

      const result = await page.evaluate(() => {
        const nav = document.querySelector('.sidebar .nav');
        nav.scrollTop = nav.scrollHeight;
        const signOut = [...document.querySelectorAll('.sidebar .nav-item')].filter((el) => /Sign Out/.test(el.textContent)).pop();
        const box = signOut.getBoundingClientRect();
        const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);

        return {
          sidebarFitsScreen: Math.round(document.querySelector('.sidebar').getBoundingClientRect().height) <= innerHeight,
          onScreen: box.top >= 0 && box.bottom <= innerHeight,
          tappable: !!hit && signOut.contains(hit),
        };
      });

      expect(result).toEqual({ sidebarFitsScreen: true, onScreen: true, tappable: true });
      await context.close();
    });
  }
}

test('a browser without dvh is handed the visible height', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 700 }, isMobile: true, hasTouch: true });
  await context.addInitScript(() => {
    const supports = CSS.supports.bind(CSS);
    CSS.supports = (property, value) => (String(value).includes('dvh') ? false : supports(property, value));
  });
  const page = await context.newPage();
  await page.goto('/__dev-login-student/dashboard');

  expect(await page.evaluate(() => document.documentElement.style.getPropertyValue('--app-height'))).toBe('700px');

  // And it follows the screen, as the bars come and go.
  await page.setViewportSize({ width: 390, height: 620 });
  await page.waitForTimeout(200);
  expect(await page.evaluate(() => document.documentElement.style.getPropertyValue('--app-height'))).toBe('620px');
  await context.close();
});
