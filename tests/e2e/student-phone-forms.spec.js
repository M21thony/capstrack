import { expect, test } from '@playwright/test';

/**
 * Forms in the student portal on a phone.
 *
 * - A pop-up fits the screen: its last button can be reached, on a phone as
 *   small as 320x568.
 * - A field being typed into stays above the on-screen keyboard with its
 *   label, and the tab bar gets out of the keyboard's way.
 *
 * There is no real keyboard in a test browser, so both kinds of phone are
 * acted out. Android Chrome shrinks the page for its keyboard (the student
 * pages ask it to), which is the window getting shorter. iPhone Safari keeps
 * the page its full height and shrinks only window.visualViewport, which is
 * swapped here for one the test can shrink.
 */

const signIn = async (page, at) => {
  const response = await page.goto(`/__dev-login-student/${at}`);
  test.skip(response.status() === 404, 'there is no student in this database to sign in as');
  // The first-visit tour sits over everything; it is not what is under test.
  await page.evaluate(() => document.querySelectorAll('#portal-tour').forEach((el) => el.remove()));
};

const fakeIphoneKeyboard = () => {
  const target = new EventTarget();
  const state = { height: null };
  const viewport = {
    get height() { return state.height === null ? innerHeight : state.height; },
    get width() { return innerWidth; },
    get offsetTop() { return 0; },
    get offsetLeft() { return 0; },
    get scale() { return 1; },
    addEventListener: target.addEventListener.bind(target),
    removeEventListener: target.removeEventListener.bind(target),
  };
  Object.defineProperty(window, 'visualViewport', { get: () => viewport, configurable: true });
  window.__keyboard = (height) => { state.height = height; target.dispatchEvent(new Event('resize')); };
};

/** Where the field and its label are against the part of the screen still showing. */
const placement = (page, selector) => page.evaluate((selector) => {
  const field = document.querySelector(selector);
  const label = field.closest('.form-group') || field.previousElementSibling || field;
  const top = window.visualViewport.offsetTop;
  const bottom = top + window.visualViewport.height;
  const f = field.getBoundingClientRect();
  const l = label.getBoundingClientRect();
  const bar = document.querySelector('.mtab');

  return {
    focused: document.activeElement === field,
    fieldShowing: f.top >= top - 1 && f.top < bottom - 20,
    labelShowing: l.top >= top - 1 && l.top < bottom,
    tabBarShowing: !!bar && getComputedStyle(bar).display !== 'none',
  };
}, selector);

const openExpertApply = async (page) => {
  await page.evaluate(() => openModal('apply-modal'));
  await page.locator('#apply-message').focus();
};

for (const [width, height] of [[320, 568], [360, 640]]) {
  test(`the recruitment post fits a ${width}x${height} phone, one intro box and its buttons in reach`, async ({ page }) => {
    await page.setViewportSize({ width, height });
    await signIn(page, 'team');
    await page.evaluate(() => openPostModal());
    await page.waitForTimeout(500);

    const card = page.locator('#postModal > .card');
    const box = await card.boundingBox();
    expect(box.y + box.height, 'the sheet runs off the bottom of the screen').toBeLessThanOrEqual(height + 1);

    // Only the introduction for the goal that is picked.
    await expect(page.locator('#leader-looking-for')).toBeVisible();
    await expect(page.locator('#individual-intro')).toBeHidden();

    const publish = page.locator('#form-submit-btn');
    await publish.scrollIntoViewIfNeeded();
    await expect(publish).toBeInViewport({ ratio: 1 });
  });
}

test('on an iPhone the field and its label stay above the keyboard', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 360, height: 640 }, isMobile: true, hasTouch: true });
  await context.addInitScript(fakeIphoneKeyboard);
  const page = await context.newPage();

  await signIn(page, 'experts');
  await openExpertApply(page);
  await page.evaluate(() => window.__keyboard(330));
  await page.waitForTimeout(800);

  expect(await placement(page, '#apply-message')).toEqual({ focused: true, fieldShowing: true, labelShowing: true, tabBarShowing: false });

  // Closing the keyboard puts everything back.
  await page.evaluate(() => { document.activeElement.blur(); window.__keyboard(null); });
  await page.waitForTimeout(400);
  expect(await page.evaluate(() => document.documentElement.classList.contains('kb-open'))).toBe(false);
  expect(await page.evaluate(() => /height/.test(document.getElementById('apply-modal').style.cssText))).toBe(false);
  await context.close();
});

test('on Android the page shrinks for the keyboard and the tab bar steps aside', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 360, height: 640 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();

  await signIn(page, 'experts');
  await openExpertApply(page);
  await page.setViewportSize({ width: 360, height: 360 });
  await page.waitForTimeout(800);

  expect(await placement(page, '#apply-message')).toEqual({ focused: true, fieldShowing: true, labelShowing: true, tabBarShowing: false });
  await context.close();
});

/**
 * The floating bar: out of the way while reading down the page, back on the
 * way up, and never over the last thing on the page once scrolled to the end.
 */
test('the student bar floats, hides reading down and comes back reading up', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await signIn(page, 'dashboard');

  const bar = page.locator('.mtab.is-floating');
  await expect(bar).toBeVisible();
  const box = await bar.boundingBox();
  expect(box.x, 'the pill stands off the left edge').toBeGreaterThan(0);
  expect(box.y + box.height, 'the pill stands off the bottom').toBeLessThan(844);

  // Scrolled as a finger would, a little at a time.
  const scroll = (distance) => page.evaluate(async (distance) => {
    const box = document.querySelector('.mtab-spacer').parentElement;
    for (let i = 0; i < 8; i++) { box.scrollTop += distance / 8; await new Promise((r) => setTimeout(r, 30)); }
  }, distance);
  const away = () => page.evaluate(() => document.querySelector('.mtab').classList.contains('is-away'));

  test.skip(await page.evaluate(() => { const s = document.querySelector('.mtab-spacer')?.parentElement; return !s || s.scrollHeight - s.clientHeight < 300; }), 'this page is too short to scroll');

  await scroll(400);
  expect(await away(), 'hidden while reading down').toBe(true);
  await scroll(-120);
  expect(await away(), 'back on the way up').toBe(false);

  // At the very end, the last thing on the page clears the bar.
  await page.evaluate(() => { const s = document.querySelector('.mtab-spacer').parentElement; s.scrollTop = s.scrollHeight; });
  await page.waitForTimeout(300);
  const credits = await page.locator('.legal-credits').boundingBox();
  const barTop = (await bar.boundingBox()).y;
  expect(credits.y + credits.height, 'the credits end under the bar').toBeLessThanOrEqual(barTop);
  await context.close();
});

test('Financial Records on a phone shows every member, not a sliver of the list', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 640 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await signIn(page, 'financial');
  await page.waitForTimeout(800);

  const list = await page.evaluate(() => { const s = document.querySelector('.fx-scroll'); return s ? { shown: s.clientHeight, whole: s.scrollHeight } : null; });
  test.skip(!list, 'no member list on this page');
  expect(list.shown).toBe(list.whole);
  await context.close();
});
