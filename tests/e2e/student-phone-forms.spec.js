import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

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

// The apply form for the first expert this team can apply to, from Find experts.
const openApply = async (page) => {
  await page.goto('/student/experts?tab=find');
  await page.evaluate(() => document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove()));
  const apply = page.locator('[data-xh="apply"]:visible').first();
  test.skip(await apply.count() === 0, 'this team has nobody it can apply to today');
  await apply.click();
  await expect(page.locator('dialog[open] #xh-apply-msg')).toBeVisible();
};
const openExpertApply = async (page) => {
  await openApply(page);
  await page.locator('#xh-apply-msg').focus();
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

/**
 * The apply form. Student 24 leads team 119, which can apply once confirmed:
 * confirmed for these three only, and put back after. Nothing is sent.
 */
test.describe('the apply form on a phone', () => {
test.describe.configure({ mode: 'serial' });
const confirmed = (value) => {
  const out = execFileSync('php', ['artisan', 'tinker', '--execute', `$t = App\\Models\\Team::find(119); echo '@@'.json_encode($t?->getRawOriginal('is_confirmed')); ${value === undefined ? '' : `$t?->update(['is_confirmed' => ${JSON.stringify(value)}]);`}`], { encoding: 'utf8' });
  return JSON.parse(out.slice(out.lastIndexOf('@@') + 2).trim());
};
let was;
test.beforeAll(() => { was = confirmed(1); });
test.afterAll(() => { if (was !== undefined) { confirmed(was); } });

test('on an iPhone the field and its label stay above the keyboard', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 360, height: 640 }, isMobile: true, hasTouch: true });
  await context.addInitScript(fakeIphoneKeyboard);
  const page = await context.newPage();

  await signIn(page, 'experts?as=24');
  await openExpertApply(page);
  await page.evaluate(() => window.__keyboard(330));
  await page.waitForTimeout(800);

  expect(await placement(page, '#xh-apply-msg')).toEqual({ focused: true, fieldShowing: true, labelShowing: true, tabBarShowing: false });

  // Closing the keyboard puts everything back.
  await page.evaluate(() => { document.activeElement.blur(); window.__keyboard(null); });
  await page.waitForTimeout(400);
  expect(await page.evaluate(() => document.documentElement.classList.contains('kb-open'))).toBe(false);
  expect(await page.evaluate(() => /height/.test(document.querySelector('dialog[open]').style.cssText))).toBe(false);
  await context.close();
});

test('on Android the page shrinks for the keyboard and the tab bar steps aside', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 360, height: 640 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();

  await signIn(page, 'experts?as=24');
  await openExpertApply(page);
  await page.setViewportSize({ width: 360, height: 360 });
  await page.waitForTimeout(800);

  expect(await placement(page, '#xh-apply-msg')).toEqual({ focused: true, fieldShowing: true, labelShowing: true, tabBarShowing: false });
  await context.close();
});

/** A pop-up's last button used to sit under the floating bar; the bar steps aside while one is open. */
test('the floating bar steps aside while a pop-up is open', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await signIn(page, 'experts?as=24');
  await page.waitForTimeout(800);
  const covered = () => page.evaluate(() => document.querySelector('.mtab').classList.contains('is-covered'));
  expect(await covered()).toBe(false);

  await openApply(page);
  await page.waitForTimeout(700);
  expect(await covered(), 'the bar is out of the way').toBe(true);
  const reachable = await page.evaluate(() => {
    const button = document.querySelector('dialog[open] [data-xh="apply-review"]');
    button.scrollIntoView({ block: 'end' });
    const box = button.getBoundingClientRect();
    return button.contains(document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2));
  });
  expect(reachable, 'Review application can be tapped').toBe(true);

  await page.locator('dialog[open] [data-xh="close"]').last().click();
  await page.waitForTimeout(700);
  expect(await covered(), 'back once it closes').toBe(false);
  await context.close();
});
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

/**
 * Tapping a tab glides the highlight there, once. The page it opens starts
 * with the highlight already in place: the glide is not played again while
 * that page loads.
 */
test('the highlight glides on the tap and is not replayed on the page it opens', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await signIn(page, 'dashboard');

  const glideX = () => page.evaluate(() => new DOMMatrix(getComputedStyle(document.querySelector('.mtab-glide')).transform).m41);
  const onTab = () => page.evaluate(() => document.querySelector('.mtab-item.on .lb')?.textContent);
  expect(await onTab()).toBe('Home');
  expect(await glideX()).toBe(0);

  // On the tap, before the next page is there, it is already on its way.
  // Noted in the page itself, since the page is on its way out. The next page
  // is held back to leave time to look; its early fetch on the finger landing
  // (the speculation rules) is turned off, or it would arrive past the hold.
  await page.evaluate(() => document.querySelectorAll('script[type="speculationrules"]').forEach((el) => el.remove()));
  await page.route('**/*', async (route) => {
    if (route.request().isNavigationRequest()) await new Promise((r) => setTimeout(r, 1200));
    await route.continue();
  });
  await page.evaluate(() => {
    const link = [...document.querySelectorAll('.mtab-item')].find((el) => /Progress/.test(el.textContent));
    link.addEventListener('click', () => setTimeout(() => {
      const glide = new DOMMatrix(getComputedStyle(document.querySelector('.mtab-glide')).transform).m41;
      const on = document.querySelector('.mtab-item.on .lb').textContent;
      sessionStorage.setItem('__tap', JSON.stringify({ glide, on }));
    }, 250));
  });
  await page.locator('.mtab-item', { hasText: 'Progress' }).tap();

  // The page it opens: at Progress from its first frame, and staying put.
  await page.waitForURL(/progress/);
  await page.waitForSelector('.mtab-glide');
  const seen = await page.evaluate(async () => {
    const glide = document.querySelector('.mtab-glide');
    const xs = [];
    for (let n = 0; n < 12; n++) { xs.push(new DOMMatrix(getComputedStyle(glide).transform).m41); await new Promise((r) => setTimeout(r, 50)); }
    return xs;
  });
  const tap = JSON.parse(await page.evaluate(() => sessionStorage.getItem('__tap')));
  expect(tap.glide, 'it moves on the tap').toBeGreaterThan(0);
  expect(tap.on).toBe('Progress');
  const settled = await page.evaluate(() => document.querySelector('.mtab-item').getBoundingClientRect().width * 3);
  for (const x of seen) expect(x, 'no second glide on arrival').toBeCloseTo(settled, 0);
  expect(await onTab()).toBe('Progress');
  await context.close();
});

/**
 * The upload sheet shows everything it holds on a phone - on an iPhone it
 * came up as a header, a sliver and its buttons - and its buttons stay on
 * the screen.
 */
for (const [width, height] of [[390, 844], [320, 568]]) {
  test(`the upload sheet shows its whole form on a ${width}x${height} phone`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width, height }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    await signIn(page, 'submission');
    await page.evaluate(() => { document.querySelectorAll('.gt, .gt-shield').forEach((el) => el.remove()); document.body.classList.remove('tour-open'); });
    await page.evaluate(() => window.openSubmissionDrawer());
    await page.waitForTimeout(500);

    const sheet = await page.evaluate(() => {
      const d = document.querySelector('dialog.up[open]');
      const body = d.querySelector('.up-body');
      const foot = d.querySelector('.up-foot').getBoundingClientRect();
      return { shown: body.clientHeight, whole: body.scrollHeight, footBottom: foot.bottom, top: d.getBoundingClientRect().top };
    });
    // All of it where it fits; where it does not, as much as the screen holds.
    if (sheet.top > 20) expect(sheet.shown, 'the form is squeezed out of the sheet').toBe(sheet.whole);
    else expect(sheet.shown).toBeGreaterThan(200);
    expect(sheet.footBottom).toBeLessThanOrEqual(height + 1);
    // The phone's Submit is the same one screen as Documents': the file first, no "What is this for?".
    await expect(page.locator('dialog.up[open] .up-drop')).toBeVisible();
    await expect(page.locator('dialog.up[open] .up-body select')).toHaveCount(0);
    await context.close();
  });
}

/** On a phone the how-to is a tap on the ? away, the whole width; the fonts come from this site. */
test('the page guide opens on a phone and the fonts are the site own', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  const fromGoogle = [];
  page.on('request', (request) => { if (/fonts\.(googleapis|gstatic)\.com/.test(request.url())) fromGoogle.push(request.url()); });
  await signIn(page, 'dashboard');
  await page.evaluate(() => { document.querySelectorAll('.gt, .gt-shield').forEach((el) => el.remove()); document.body.classList.remove('tour-open'); });

  await expect(page.locator('.page-tutorial')).toHaveCount(0);
  await page.locator('#pg-btn').tap();
  const guide = page.locator('#pg');
  await expect(guide).toBeVisible();
  await expect(guide.locator('#pg-title')).toHaveText('Dashboard');
  expect((await guide.boundingBox()).width, 'the whole phone width').toBeGreaterThanOrEqual(389);

  expect(await page.evaluate(async () => { await document.fonts.ready; return document.fonts.check('500 14px "DM Sans"'); })).toBe(true);
  expect(fromGoogle).toEqual([]);
  await context.close();
});

/** Booking fits a phone: nothing runs off the side, the calendar stays inside the booking, and open days keep their colour. */
for (const [width, height] of [[390, 844], [320, 640]]) {
  test(`consultation fits a ${width}x${height} phone`, async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width, height }, isMobile: true, hasTouch: true });
    const page = await context.newPage();
    await signIn(page, 'consultation');
    await page.evaluate(() => document.querySelectorAll('.gt, .gt-shield').forEach((el) => el.remove()));
    const schedule = page.locator('[data-xh="schedule"]:visible').first();
    test.skip(await schedule.count() === 0, 'this team has no expert to book');
    await schedule.click();
    await expect(page.locator('dialog[open] .xh-cal-grid')).toBeVisible();

    const result = await page.evaluate(() => {
      const content = document.querySelector('.main .content');
      const box = document.querySelector('dialog[open]').getBoundingClientRect();
      const days = [...document.querySelectorAll('dialog[open] .xh-day:not(.is-blank)')];
      return {
        sideways: content.scrollWidth - content.clientWidth,
        pageWider: document.documentElement.scrollWidth - innerWidth,
        daysOutside: days.filter((d) => { const r = d.getBoundingClientRect(); return r.left < box.left || r.right > box.right + 0.5; }).length,
        uncolouredBookable: days.filter((d) => d.classList.contains('is-avail') && d.getAttribute('aria-pressed') !== 'true' && getComputedStyle(d).backgroundColor === 'rgba(0, 0, 0, 0)').length,
      };
    });

    expect(result).toEqual({ sideways: 0, pageWider: 0, daysOutside: 0, uncolouredBookable: 0 });
    await context.close();
  });
}

/** Financial Records opens no document upload over itself, and a receipt photo can be chosen. */
test('Financial Records on a phone: no document upload pop-up, and a photo can be chosen', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  await signIn(page, 'financial');
  await page.waitForTimeout(1500);
  expect(await page.evaluate(() => !!document.querySelector('dialog.up[open]')), 'the document upload stayed shut').toBe(false);

  // The receipt form is part of the page, shown while there is a receipt to send.
  const form = page.locator('#u-form');
  test.skip(!(await form.isVisible()), 'no receipt to send in this database');
  expect(await page.locator('#u-file').getAttribute('capture')).toBeNull();
  await expect(page.locator('#u-send')).toBeDisabled();
  await page.setInputFiles('#u-file', { name: 'receipt.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') });
  await expect(page.locator('#u-pick-t')).toHaveText('receipt.png');
  await expect(page.locator('#u-send')).toBeEnabled();
  expect(await page.evaluate(() => !!document.querySelector('dialog.up[open]'))).toBe(false);
  await context.close();
});
