// Student Experts page: the two tabs, booking a time with the deliverables
// checklist, and the type-ahead for a replacement expert. Needs a local DB
// where student 131's adviser has at least one open weekly time, and the
// team has an approved expert. Nothing is sent - dialogs are closed unsent.
import { test, expect } from '@playwright/test';

const AS = process.env.EXPERTS_STUDENT || '131';
const open = async (page, tab = '') => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`/__dev-login-student/experts?as=${AS}`);
  await expect(page, 'signed in as a student').not.toHaveURL(/\/login/);
  if (tab) { await page.goto(`/student/experts?tab=${tab}`); }
  // The first-visit tour sits over everything; it is not what is under test.
  await page.evaluate(() => { document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove()); document.body.classList.remove('tour-open'); });
  await expect(page.locator('#csk')).toHaveCount(0);
};
const shot = async (page, testInfo, name) => {
  await expect(page.locator('#csk'), 'grey loading cover gone').toHaveCount(0);
  const path = testInfo.outputPath(`${name}.png`);
  await page.screenshot({ path });
  return testInfo.attach(name, { path, contentType: 'image/png' });
};
const noSidewaysScroll = async (page) => {
  const over = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  expect(over, 'no sideways scroll').toBeLessThanOrEqual(0);
};

test('two tabs switch, and Schedule opens and closes with Escape', async ({ page }, testInfo) => {
  await open(page);
  const book = page.locator('#xh-tab-book');
  const find = page.locator('#xh-tab-find');
  await expect(book).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator('#xh-panel-book')).toBeVisible();
  await expect(page.locator('#xh-panel-find')).toBeHidden();
  await noSidewaysScroll(page);
  await shot(page, testInfo, '1-your-experts');

  await find.click();
  await expect(find).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator('#xh-panel-find')).toBeVisible();
  await expect(page.locator('#xh-panel-book')).toBeHidden();
  await noSidewaysScroll(page);
  // The quick guide folds; opened it shows three steps and the CaPSA/TSA rule.
  const guide = page.locator('.xh-guide');
  if (!(await guide.evaluate((d) => d.open))) { await guide.locator('summary').click(); }
  await expect(guide.locator('li')).toHaveCount(3);
  await expect(guide).toContainText("ask your section's CaPSA/TSA");
  await shot(page, testInfo, '2-find-experts');

  await book.click();
  await page.locator('[data-xh="schedule"][data-role]').first().click();
  const dlg = page.locator('dialog[open]');
  await expect(dlg).toContainText('Schedule a consultation');
  await page.keyboard.press('Escape');
  await expect(dlg).toHaveCount(0);
});

test('find pages flip with the arrows when there is more than one page', async ({ page }) => {
  await open(page, 'find');
  if (await page.locator('#xh-pgnav').isHidden()) { test.info().annotations.push({ type: 'note', description: 'one page only' }); return; }
  const range = page.locator('#xh-pg-range');
  const before = (await range.textContent()) || '';
  await page.locator('[data-xh="page"][data-dir="1"]').click();
  await expect(range).not.toHaveText(before);
  await page.locator('[data-xh="page"][data-dir="-1"]').click();
  await expect(range).toHaveText(before);
});

test('booking asks only which deliverables, as a checklist', async ({ page }, testInfo) => {
  await open(page);
  await page.locator('[data-xh="schedule"][data-role="adviser"]').click();
  const cal = page.locator('dialog[open]');
  await cal.locator('[data-xh="date"]:not([disabled])').first().click();
  await cal.locator('[data-xh="slot"]').first().click();
  // Book this time opens on top of the calendar.
  const dlg = page.locator('dialog[open]').last();
  await expect(dlg).toContainText('Book this time');
  await expect(dlg.locator('[data-xh="topic"]'), 'no topic choice any more').toHaveCount(0);
  await expect(dlg.locator('textarea'), 'no note any more').toHaveCount(0);

  const send = dlg.locator('#xh-book-send');
  const sum = dlg.locator('#xh-deliv-sum');
  await expect(send).toBeDisabled();
  await expect(sum).toHaveText('Choose deliverables');

  await dlg.locator('#xh-deliv-dd > summary').click();
  await dlg.locator('.xh-dd-item', { hasText: 'Chapters 1–3, together' }).click();
  await dlg.locator('.xh-dd-item', { hasText: 'Logical diagram' }).click();
  await expect(dlg.locator('.xh-dd-item', { hasText: 'Prototype' })).not.toContainText('%');
  await expect(dlg.locator('#xh-deliv-other')).toBeHidden();
  await dlg.locator('.xh-dd-item', { hasText: 'Others' }).click();
  await expect(dlg.locator('#xh-deliv-other')).toBeFocused();
  await page.keyboard.type('Gantt chart');
  await expect(sum).toHaveText('Chapters 1–3, Logical diagram, Others: Gantt chart');
  await expect(send).toBeEnabled();
  await shot(page, testInfo, '3-book-checklist');

  // A click outside folds the list away and keeps the picks.
  await dlg.locator('#xh-deliv-h').click();
  await expect(dlg.locator('#xh-deliv-dd')).not.toHaveAttribute('open', '');
  await expect(sum).toHaveText('Chapters 1–3, Logical diagram, Others: Gantt chart');
  await page.keyboard.press('Escape');
});

test('the replacement expert is found by typing a name', async ({ page }, testInfo) => {
  await open(page, 'find');
  const change = page.locator('[data-xh="change"]').first();
  if (!(await change.count())) { test.info().annotations.push({ type: 'note', description: 'no approved expert in this role' }); return; }
  await change.click();
  const dlg = page.locator('dialog[open]');
  const q = dlg.locator('#xh-change-q');
  await expect(q).toBeVisible();
  await expect(dlg.locator('select'), 'no long dropdown').toHaveCount(0);
  await q.fill('an');
  const opts = dlg.locator('.xh-ta-opt');
  expect(await opts.count()).toBeGreaterThan(0);
  expect(await opts.count()).toBeLessThanOrEqual(6);
  await expect(opts.first().locator('u')).toHaveText(/an/i);
  await expect(opts.first()).toHaveAttribute('aria-selected', 'true');
  await shot(page, testInfo, '4-change-typeahead');

  await page.keyboard.press('Enter');
  await expect(dlg, 'Enter picks, it does not send').toHaveCount(1);
  await expect(q).toBeHidden();
  await expect(dlg.locator('#xh-ta-picked')).toBeVisible();
  expect(await dlg.locator('#xh-change-to').inputValue()).not.toBe('');
  await shot(page, testInfo, '5-change-picked');

  await dlg.locator('[data-xh="ta-clear"]').click();
  await expect(q).toBeFocused();
  expect(await dlg.locator('#xh-change-to').inputValue()).toBe('');
  await expect(dlg.locator('#xh-change-send')).toBeDisabled();
  await page.keyboard.press('Escape');
});

test('phone width keeps both tabs and no sideways scroll', async ({ page }, testInfo) => {
  await open(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('#xh-tab-book')).toBeVisible();
  await expect(page.locator('#xh-tab-find')).toBeVisible();
  await noSidewaysScroll(page);
  await shot(page, testInfo, '6-phone');

  // Find experts at tablet and phone width: the three role cards fit.
  for (const [w, h, name] of [[768, 1024, '7-tablet-find'], [390, 844, '8-phone-find']]) {
    await page.setViewportSize({ width: w, height: h });
    await page.goto('/student/experts?tab=find');
    await page.evaluate(() => { document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove()); document.body.classList.remove('tour-open'); });
    await noSidewaysScroll(page);
    const strip = await page.locator('#xh-strip').evaluate((el) => el.scrollWidth - el.clientWidth);
    expect(strip, 'role cards not cut off').toBeLessThanOrEqual(0);
    await shot(page, testInfo, name);
  }
});
