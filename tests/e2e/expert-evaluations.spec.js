// Expert evaluations: the student's one-sheet form (every expert on one
// form), CaPSA's results summary, and the page for one expert. Needs a local
// DB with at least one team owing evaluations and one expert already rated.
// Nothing is submitted - the student form is only filled in, never sent.
import { test, expect } from '@playwright/test';

const student = async (page, at = '', as = '') => { await page.setViewportSize({ width: 1440, height: 900 }); await page.goto(`/__dev-login-student/${at}${as ? `?as=${as}` : ''}`); await expect(page, 'signed in as a student').not.toHaveURL(/\/login/); };
const staff = async (page, role, at = '') => { await page.setViewportSize({ width: 1440, height: 900 }); await page.goto(`/__dev-login-expert/${role}/${at}`); await expect(page, `signed in as ${role}`).not.toHaveURL(/\/login/); };
// Waits for the grey loading cover to lift, so the picture shows the page.
const shot = async (page, testInfo, name) => { await expect(page.locator('#csk')).toHaveCount(0); return testInfo.attach(name, { body: await page.screenshot(), contentType: 'image/png' }); };

test('the student rates every expert on one sheet and the counts keep up', async ({ page }, testInfo) => {
  await student(page, 'experts', process.env.EVAL_STUDENT || '131');
  await page.goto('/student/experts?tab=evaluations');
  // The first-visit tour sits over everything; it is not what is under test.
  await page.evaluate(() => { document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove()); document.body.classList.remove('tour-open'); });
  const form = page.locator('.xh-ev-form').first();
  await expect(form, 'a team that owes evaluations sees the sheet').toBeVisible();

  const cards = form.locator('.xh-ex');
  const experts = await cards.count();
  expect(experts).toBeGreaterThan(0);
  await expect(form.locator('[data-done]')).toHaveText('0');
  await expect(form.locator('[data-left]')).toHaveText(/^0 of \d+ questions answered$/);

  // Only the first expert starts open.
  await expect(cards.first()).toHaveAttribute('open', '');
  if (experts > 1) { await expect(cards.nth(1)).not.toHaveAttribute('open', ''); }

  // Answer every row of the first expert: that expert reads Done, the sheet counts it.
  const first = cards.first();
  const rows = first.locator('.xh-ev-q');
  const n = await rows.count();
  await rows.first().locator('label').nth(3).click();
  await expect(first.locator('[data-status]')).toHaveText(n === 1 ? 'Done' : `${n - 1} left`);
  for (let i = 1; i < n; i++) { await rows.nth(i).locator('label').nth(4).click(); }
  await expect(first.locator('[data-status]')).toHaveText('Done');
  await expect(form.locator('[data-done]')).toHaveText('1');
  await expect(form.locator('[data-left]')).toHaveText(new RegExp(`^${n} of \\d+ questions answered$`));

  // "Next" opens the following expert and closes this one.
  if (experts > 1) {
    await first.locator('[data-next]').click();
    await expect(cards.nth(1)).toHaveAttribute('open', '');
    await expect(first).not.toHaveAttribute('open', '');
  }
  await shot(page, testInfo, 'student-sheet');
});

test('CaPSA reads the summary, opens an expert and comes back by the breadcrumb', async ({ page }, testInfo) => {
  await staff(page, 'capsa', 'evaluations');
  await page.goto('/capsa/evaluations?tab=results');
  await expect(page.locator('.ev-sum-strip'), 'the summary strip').toBeVisible();
  await expect(page.locator('.ev-sum-strip')).toContainText('evaluations in');
  const rated = page.locator('a.ev-sum-x');
  expect(await rated.count(), 'at least one rated expert').toBeGreaterThan(0);
  await expect(rated.first().locator('.ev-sum-score')).toHaveText(/^\d\.\d$/);
  await shot(page, testInfo, 'capsa-summary');

  const name = ((await rated.first().locator('b').textContent()) || '').trim();
  await rated.first().click();
  await expect(page).toHaveURL(/\/capsa\/evaluations\/experts\/\d+/);
  await expect(page.locator('.ev-lk-head h1')).toHaveText(name);
  await expect(page.locator('.ev-lk-nums')).toContainText('/ 5');
  const bars = page.locator('.ev-lk-bar');
  expect(await bars.count(), 'one bar per question').toBeGreaterThan(0);
  await expect(bars.first()).toHaveAttribute('aria-label', /^\d+ Poor, \d+ Fair, \d+ Good, \d+ Very good, \d+ Excellent$/);
  await expect(page.locator('.ev-resps').first(), 'what teams said').toBeVisible();
  await expect(page.locator('main')).not.toContainText(/@[a-z0-9.-]+\.[a-z]{2,}/i);
  await shot(page, testInfo, 'capsa-expert');

  await page.locator('.ev-crumb a', { hasText: 'Results' }).click();
  await expect(page).toHaveURL(/tab=results/);
  await expect(page.locator('.ev-sum-strip')).toBeVisible();
});

test('an expert page the CaPSA has no answers for is not found', async ({ page }) => {
  await staff(page, 'capsa', 'evaluations');
  const res = await page.goto('/capsa/evaluations/experts/999999');
  expect(res.status()).toBe(404);
});
