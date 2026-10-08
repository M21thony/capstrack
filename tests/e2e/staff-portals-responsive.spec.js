import { expect, test } from '@playwright/test';

/**
 * The expert and CaPSA portals on a phone and a tablet: pages open finished
 * and hold still, every list's buttons are on the screen, and the menu on a
 * tablet slides in rather than taking a third of the page.
 */

const shifts = () => {
  window.__shift = 0;
  new PerformanceObserver((list) => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.__shift += e.value; })
    .observe({ type: 'layout-shift', buffered: true });
};

const noTour = (page) => page.evaluate(() => {
  document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove());
  document.body.classList.remove('tour-open');
});

async function phone(browser, width = 390, height = 844) {
  const context = await browser.newContext({ viewport: { width, height }, isMobile: width < 800, hasTouch: true });
  await context.addInitScript(shifts);
  return { context, page: await context.newPage() };
}

async function signIn(page, role, at = 'dashboard') {
  const response = await page.goto(`/__dev-login-expert/${role}/${at}`);
  test.skip(response.status() === 404, `there is no ${role} account in this database`);
}

// Arrived at from another page of the portal, the way they are opened: the
// page is drawn as soon as it can be, so anything that comes in after that
// shows as a jump.
for (const [role, at] of [['capsa', 'financial'], ['capsa', 'resources'], ['capsa', 'rubric_management'], ['capsa', 'sections'], ['capsa', 'experts'], ['capsa', 'defense_scheduling'], ['capsa', 'panel_assignment'], ['adviser', 'profile'], ['capsa', 'profile']]) {
  test(`${role}/${at} opens on a phone without a jump`, async ({ browser }) => {
    const { context, page } = await phone(browser);
    await signIn(page, role);
    await page.waitForTimeout(600);
    await signIn(page, role, at);
    await page.waitForTimeout(1500);
    expect(await page.evaluate(() => window.__shift), 'layout shift while opening').toBeLessThan(0.02);
    await context.close();
  });
}

test('Team Assignments on a phone opens at the queue, and a tapped team shows its buttons', async ({ browser }) => {
  const { context, page } = await phone(browser);
  await signIn(page, 'adviser', 'assignments');
  await page.waitForTimeout(1200);
  await noTour(page);

  // One scrolling area, and it is at the top: the application picked for the
  // reader is not scrolled to.
  const scrolled = await page.evaluate(() => [...document.querySelectorAll('.main *')]
    .filter((el) => /(auto|scroll)/.test(getComputedStyle(el).overflowY) && el.scrollHeight > el.clientHeight + 4 && el.clientHeight > innerHeight * 0.4)
    .map((el) => ({ top: el.scrollTop })));
  expect(scrolled.length, 'no scrolling area inside the page\'s own').toBeLessThanOrEqual(1);
  for (const s of scrolled) expect(s.top).toBe(0);

  const row = page.locator('.ta-row').first();
  test.skip(!(await row.count()), 'no team has asked this adviser');
  const name = await row.locator('.ta-team').first().evaluate((el) => ({ shown: el.clientWidth, whole: el.scrollWidth }));
  expect(name.shown, 'the team name is not cut to a few letters').toBeGreaterThan(90);

  await row.locator('.ta-row-btn').click();
  await page.waitForTimeout(900);
  const acts = page.locator('#asg-acts .ta-btn').last();
  if (await acts.count()) {
    await acts.scrollIntoViewIfNeeded();
    const box = await acts.boundingBox();
    expect(box.y + box.height).toBeLessThanOrEqual(844);
    expect(box.y).toBeGreaterThanOrEqual(0);
  }
  await context.close();
});

test('the Activity Log page controls sit above the tab bar on a phone', async ({ browser }) => {
  const { context, page } = await phone(browser);
  await signIn(page, 'capsa', 'activitylog');
  await page.waitForTimeout(1200);
  await noTour(page);
  const foot = await page.locator('.list-foot').boundingBox();
  const bar = await page.locator('.mtab').boundingBox();
  expect(foot.y + foot.height, 'Prev and Next are not under the pill').toBeLessThanOrEqual(bar.y);
  await context.close();
});

test('a team card on a phone keeps its arrow beside the name', async ({ browser }) => {
  const { context, page } = await phone(browser);
  await signIn(page, 'adviser', 'teams');
  await page.waitForTimeout(1000);
  const row = page.locator('.team-role-panel.active .team-row').first();
  test.skip(!(await row.count()), 'this adviser has no teams');
  // On the line of the name block, to its right - not in a row of its own
  // under the progress bar.
  const [chev, who] = await Promise.all([row.locator('.tr-chev').boundingBox(), row.locator('.tr-who').boundingBox()]);
  const middle = chev.y + chev.height / 2;
  expect(middle).toBeGreaterThan(who.y);
  expect(middle).toBeLessThan(who.y + who.height);
  expect(chev.x).toBeGreaterThan(who.x + who.width - 1);
  await context.close();
});

test('CaPSA Financial on a phone gives the member their name', async ({ browser }) => {
  const { context, page } = await phone(browser);
  await signIn(page, 'capsa', 'financial');
  await page.waitForTimeout(1000);
  const cell = page.locator('#c-rows tr.fx-row td:nth-child(2)').first();
  test.skip(!(await cell.count()), 'no receipts in this section');
  const width = await cell.evaluate((el) => el.getBoundingClientRect().width);
  expect(width).toBeGreaterThan(160);
  await context.close();
});

// A tablet in portrait: the page gets the whole width, and the menu is a
// drawer behind the menu button. A computer keeps the sidebar open.
for (const [width, height] of [[820, 1180], [1024, 768]]) {
  test(`on a ${width}px tablet the sidebar slides in from the menu button`, async ({ browser }) => {
    const { context, page } = await phone(browser, width, height);
    await signIn(page, 'capsa', 'sections');
    await page.waitForTimeout(800);
    await noTour(page);
    const left = () => page.evaluate(() => Math.round(document.querySelector('.sidebar').getBoundingClientRect().left));
    expect(await left()).toBeLessThan(-100);
    expect(await page.evaluate(() => Math.round(document.querySelector('.main').getBoundingClientRect().width))).toBe(width);

    await page.locator('.topbar .hamburger-btn').click();
    await expect.poll(left).toBe(0);
    await page.mouse.click(width - 20, height / 2);
    await expect.poll(left).toBeLessThan(-100);
    await context.close();
  });
}

test('on a computer the sidebar stays open and there is no menu button', async ({ browser }) => {
  const { context, page } = await phone(browser, 1440, 900);
  await signIn(page, 'capsa', 'sections');
  await page.waitForTimeout(600);
  expect(await page.evaluate(() => Math.round(document.querySelector('.sidebar').getBoundingClientRect().left))).toBe(0);
  await expect(page.locator('.topbar .hamburger-btn')).toBeHidden();
  await context.close();
});

// Document Review and Team Grading were six-column tables wider than a tablet:
// the status and the buttons to open a document or grade were off the edge.
for (const [at, rowSel] of [['docreview', 'tr.dr-row'], ['grading', '.gl tbody tr:not(.gl-none)']]) {
  for (const [width, height] of [[768, 1024], [820, 1180], [1024, 768]]) {
    test(`${at} on a ${width}px tablet keeps every row's button on the screen`, async ({ browser }) => {
      const { context, page } = await phone(browser, width, height);
      await signIn(page, 'adviser', at);
      await page.waitForTimeout(900);
      await noTour(page);
      const rows = page.locator(rowSel);
      test.skip(!(await rows.count()), `nothing on ${at} for this adviser`);
      const overflow = await rows.evaluateAll((els) => els.map((el) => {
        const right = Math.max(...[...el.querySelectorAll('a, button')].map((b) => b.getBoundingClientRect().right));
        return right - innerWidth;
      }));
      for (const o of overflow) expect(o, 'a button past the right edge').toBeLessThanOrEqual(0);
      await context.close();
    });
  }
}

// The dashboard tiles: an expert's are counts only; a CaPSA's open the page
// they name. Their addresses were written with the quotes inside them, and
// every tile opened ".../%22http://...%22", a page that is not there.
test('the expert dashboard tiles are counts, and a CaPSA tile opens its page', async ({ browser }) => {
  const { context, page } = await phone(browser, 1440, 900);
  await signIn(page, 'adviser');
  await page.waitForTimeout(600);
  expect(await page.locator('.content a.stat-chip').count()).toBe(0);
  expect(await page.locator('.content div.stat-chip').count()).toBeGreaterThanOrEqual(3);

  await signIn(page, 'capsa');
  await page.waitForTimeout(600);
  await noTour(page);
  const tile = page.locator('a.stat-chip').first();
  const href = await tile.getAttribute('href');
  expect(href).not.toContain('"');
  const [response] = await Promise.all([page.waitForNavigation(), tile.click()]);
  expect(response.status()).toBe(200);
  expect(new URL(page.url()).pathname).toBe(new URL(href).pathname);
  await context.close();
});

// The profile from the floating bar on a phone: the avatar opens the menu,
// the name card at its top opens the profile - whole, on screen, Save
// within reach - and a tap on the page behind closes it again.
test('on a phone the expert profile opens from the floating bar as a page of its own', async ({ browser }) => {
  const { context, page } = await phone(browser, 360, 640);
  await signIn(page, 'adviser', 'progress');
  await page.waitForTimeout(900);
  await noTour(page);
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  await page.locator('.mtab .mtab-item').last().tap();
  await expect.poll(() => page.evaluate(() => Math.round(document.querySelector('.sidebar').getBoundingClientRect().left))).toBe(0);
  await page.locator('.sidebar .sidebar-user').tap();
  await page.waitForURL(/\/instructor\/profile$/);
  await page.waitForTimeout(900);
  await noTour(page);

  expect(await page.evaluate(() => document.documentElement.scrollWidth), 'no sideways scroll').toBeLessThanOrEqual(360);

  // A change brings up Save, clear of the floating bar.
  await page.locator('.pp-prog input').first().fill('1');
  const bar = await page.locator('.pp-savebar').boundingBox();
  const tabs = await page.locator('.mtab').boundingBox();
  expect(bar.y + bar.height, 'Save is above the floating bar').toBeLessThanOrEqual(tabs.y);
  expect(errors).toEqual([]);
  await context.close();
});

test('on a phone a CaPSA name card opens My Profile', async ({ browser }) => {
  const { context, page } = await phone(browser);
  await signIn(page, 'capsa');
  await page.waitForTimeout(800);
  await noTour(page);
  await page.locator('.mtab .mtab-item').last().tap();
  await expect.poll(() => page.evaluate(() => Math.round(document.querySelector('.sidebar').getBoundingClientRect().left))).toBe(0);
  await Promise.all([page.waitForURL(/\/capsa\/profile$/), page.locator('.sidebar .sidebar-user').tap()]);
  await context.close();
});

// A tablet's menu over Grading Rubrics, whose own dimming layer sat over it.
test('on a tablet the menu over Grading Rubrics can be tapped', async ({ browser }) => {
  const { context, page } = await phone(browser, 820, 1180);
  await signIn(page, 'capsa', 'rubric_management');
  await page.waitForTimeout(800);
  await noTour(page);
  await page.locator('.topbar .hamburger-btn').tap();
  await expect.poll(() => page.evaluate(() => Math.round(document.querySelector('.sidebar').getBoundingClientRect().left))).toBe(0);
  const link = page.locator('.sidebar a.nav-item').first();
  const box = await link.boundingBox();
  const hit = await page.evaluate(([x, y]) => !!document.elementFromPoint(x, y)?.closest('.sidebar'), [box.x + box.width / 2, box.y + box.height / 2]);
  expect(hit, 'the menu is on top').toBe(true);
  await context.close();
});
