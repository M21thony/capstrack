import { expect, test } from '@playwright/test';

/**
 * The things changed in this round, checked in a real browser.
 *
 * PHPUnit reads the HTML that was sent; none of it runs the page. Every bug
 * in this file was invisible to that: a modal 420px wide because a stylesheet
 * lost a specificity fight, a decline button that threw before it opened
 * anything, a pager that never advanced. What is asserted here is what a
 * person actually sees.
 */

const asStudent = async (page, section = '') => {
  await page.goto(`/__dev-login-student/${section}`);
  await expect(page).not.toHaveURL(/\/login/);
};

test.describe('student pages load without script errors', () => {
  for (const page_ of ['duplication', 'experts', 'team', 'review', 'submission']) {
    test(`student/${page_} has no console errors`, async ({ page }) => {
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
      });

      await asStudent(page, page_);
      await page.waitForLoadState('networkidle');

      // Missing images and blocked third-party noise are not ours; a thrown
      // exception is, and it takes every function after it with it.
      const ours = errors.filter((e) => !/favicon|net::ERR|Failed to load resource/i.test(e));
      expect(ours, `console errors on student/${page_}`).toEqual([]);
    });
  }
});

test('the original PDF opens in a panel wide enough to read', async ({ page }) => {
  await asStudent(page, 'experts');

  // The button sits inside the preview, which sits inside the apply form -
  // three panels deep, which is the whole reason its width had to be fought
  // for. Whether this team can currently apply to anybody depends on which
  // experts have set their programmes, so the panel is opened directly: the
  // width is the thing under test, not the path to it.
  test.skip(
    await page.locator('#previewDialog').count() === 0,
    'this team has no approved pre-proposal with a file'
  );

  await page.evaluate(() => window.openOriginalPdf());

  const box = page.locator('#previewDialog');
  await expect(box).toBeVisible();

  // A PDF page has a fixed aspect ratio - too narrow is unreadable rather
  // than merely cramped.
  const width = await box.evaluate((el) => el.getBoundingClientRect().width);
  expect(width).toBeGreaterThan(700);

  const height = await box.evaluate((el) => el.getBoundingClientRect().height);
  expect(height).toBeGreaterThan(500);

  // Drawn as pages, never an iframe, and closed with its own button.
  await expect(box.locator('canvas').first()).toBeVisible({ timeout: 15000 });
  await expect(box.locator('iframe')).toHaveCount(0);
  await box.getByRole('button', { name: 'Close' }).click();
  await expect(box).toBeHidden();
});

test('the expert strip pages forward and back', async ({ page }) => {
  await asStudent(page, 'experts');

  const next = page.locator('.rail-nav.next');

  test.skip(await next.count() === 0 || !(await next.isVisible()), 'everything fits on one page');

  const count = page.locator('#railCount');
  await expect(count).toContainText('1 of');

  await next.click();
  // The page number was worked out by dividing the scroll by a whole screen
  // width, so a last page holding one card rounded back to zero and it never
  // left "1 of 2".
  await expect(count).not.toContainText('1 of', { timeout: 3000 });
  await expect(page.locator('.rail-nav.prev')).toBeVisible();
});

test('a member with no role is asked once, and cannot dismiss it', async ({ page }) => {
  await asStudent(page, 'team');

  const dialog = page.locator('#roleChoiceModal');

  test.skip(await dialog.count() === 0, 'this student already has a role');

  await expect(dialog).toBeVisible();
  // No close button on purpose: every roster row and the CaPSA's own
  // verification list read this field.
  await expect(dialog.locator('.pm-close-btn, .sheet-x')).toHaveCount(0);
  // Programmer is stated, never offered.
  await expect(dialog).toContainText('Everyone on the team is a');
});

test('the profile panel carries no avatar and no sign out', async ({ page }) => {
  await asStudent(page, 'team');

  await page.locator('#profile-trigger').click();

  const panel = page.locator('#profile-modal');
  await expect(panel).toBeVisible();

  await expect(panel.locator('.pm-avatar, .pm-avatar-img')).toHaveCount(0);
  await expect(panel).not.toContainText('Sign Out');
  await expect(panel).not.toContainText('Edit Profile');

  // The close button wears no circle: two other partials style a bare
  // .pm-close-btn as one, and one of them loads after this panel.
  const radius = await panel.locator('.pm-close-btn').evaluate(
    (el) => getComputedStyle(el).borderRadius
  );
  expect(radius).toBe('0px');
});
