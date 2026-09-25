import { expect, test } from '@playwright/test';

/**
 * A long staff list scrolls inside itself on a computer: the page holds still
 * and the column headings stay at the top of the rows. The rows are copied in
 * the browser, since a test database rarely has enough of them.
 */

const LISTS = [
  ['capsa', 'capsa/team-management', '#tm-teams .tscroll'],
  ['capsa', 'capsa/activitylog', '.list-card'],
  ['admin', 'admin/activitylog', '.list-card'],
  ['adviser', 'instructor/docreview', '.dr-card'],
];

for (const [role, path, scroller] of LISTS) {
  test(`${path}: the rows scroll under headings that stay`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const response = await page.goto(`/__dev-login-expert/${role}/dashboard`);
    test.skip(response.status() === 404, `there is no ${role} account in this database`);
    await page.goto('/' + path);
    await page.waitForTimeout(1200);

    const result = await page.evaluate(async (scroller) => {
      const box = document.querySelector(scroller);
      const body = box.querySelector('tbody');
      const row = body.querySelector('tr');
      for (let i = 0; i < 40; i++) body.appendChild(row.cloneNode(true));
      await new Promise((r) => setTimeout(r, 150));
      box.scrollTop = 600;
      await new Promise((r) => setTimeout(r, 150));
      const content = document.querySelector('.content');
      return {
        rowsScrolled: box.scrollTop > 0,
        headingAtTop: Math.abs(box.querySelector('thead th').getBoundingClientRect().top - box.getBoundingClientRect().top) <= 2,
        listFitsScreen: box.getBoundingClientRect().bottom <= innerHeight,
        pageStill: content.scrollTop === 0,
      };
    }, scroller);

    expect(result).toEqual({ rowsScrolled: true, headingAtTop: true, listFitsScreen: true, pageStill: true });
  });
}

/** On a phone too, as the Section List: the team cards scroll inside the list and the page holds still. */
test('capsa/team-management on a phone: the cards scroll inside the list', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  const response = await page.goto('/__dev-login-expert/capsa/dashboard');
  test.skip(response.status() === 404, 'there is no capsa account in this database');
  await page.goto('/capsa/team-management');
  await page.waitForTimeout(1200);

  const result = await page.evaluate(async () => {
    const box = document.querySelector('#tm-teams .tscroll');
    const body = box.querySelector('tbody');
    const row = body.querySelector('tr');
    for (let i = 0; i < 30; i++) body.appendChild(row.cloneNode(true));
    await new Promise((r) => setTimeout(r, 150));
    box.scrollTop = 500;
    document.querySelector('.content').scrollTop = 500;
    await new Promise((r) => setTimeout(r, 150));
    return { listScrolled: box.scrollTop > 0, pageStill: document.querySelector('.content').scrollTop === 0, listTall: box.clientHeight > 300 };
  });

  expect(result).toEqual({ listScrolled: true, pageStill: true, listTall: true });
  await context.close();
});
