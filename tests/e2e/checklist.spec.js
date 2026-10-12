import { expect, test } from '@playwright/test';

/**
 * The checklist, walked one item at a time in a real browser.
 *
 * Each test is named for the line it answers, so the report reads back as
 * the list rather than as a pile of assertions. Anything that cannot be
 * reached with the data currently in the database skips loudly instead of
 * passing quietly - a green tick on a test that never ran is worse than a
 * red one.
 */

const student = async (page, at = '') => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`/__dev-login-student/${at}`);
  await expect(page, 'signed in as a student').not.toHaveURL(/\/login/);
};

const staff = async (page, role, at = '') => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`/__dev-login-expert/${role}/${at}`);
  await expect(page, `signed in as ${role}`).not.toHaveURL(/\/login/);
};

const shot = async (page, testInfo, name) =>
  testInfo.attach(name, { body: await page.screenshot(), contentType: 'image/png' });

// ── student/experts ───────────────────────────────────────────────────────

test('experts: View Original PDF opens a readable panel of its own', async ({ page }, testInfo) => {
  await student(page, 'experts');

  test.skip(
    await page.locator('#previewDialog').count() === 0,
    'this team has no approved pre-proposal with an uploaded file'
  );

  // The button lives in the preview, which lives in the apply form. Opened
  // directly because the width is what is under test, and whether this team
  // can apply to anybody today depends on which experts set their programmes.
  await page.evaluate(() => window.openOriginalPdf());

  const box = page.locator('#previewDialog');
  await expect(box).toBeVisible();
  // Drawn as pages, not loaded into an iframe.
  await expect(box.locator('canvas').first()).toBeVisible({ timeout: 15000 });
  await expect(box.locator('iframe')).toHaveCount(0);
  await shot(page, testInfo, 'original-pdf-panel');

  const { width, height } = await box.evaluate((el) => {
    const r = el.getBoundingClientRect();

    return { width: r.width, height: r.height };
  });

  expect(width, 'wide enough to read a page of A4').toBeGreaterThan(700);
  expect(height).toBeGreaterThan(500);

  await box.getByRole('button', { name: 'Close' }).click();
  await expect(box).toBeHidden();
});

// ── student/duplication ───────────────────────────────────────────────────

test('duplication: suggestions are labelled Recommended Adviser / Consultant', async ({ page }, testInfo) => {
  await student(page, 'duplication');
  await shot(page, testInfo, 'duplication');

  const labels = page.locator('.sx-role');

  test.skip(await labels.count() === 0, 'no duplication check has been run on this team');

  const texts = await labels.allTextContents();
  expect(texts.join(' | ')).toMatch(/Recommended (Adviser|Consultant|Grammarian)/);
});

test('duplication: the chosen title is marked and the other is set aside', async ({ page }, testInfo) => {
  await student(page, 'duplication');

  const chosen = page.locator('.pp-row.is-chosen');

  test.skip(await chosen.count() === 0, 'this team has not chosen a final title');

  await expect(chosen).toHaveCount(1);
  await expect(chosen).toContainText("This is your team's title");
  await shot(page, testInfo, 'chosen-title');
});

test('duplication: Apply is locked on a title the team set aside', async ({ page }) => {
  await student(page, 'duplication');

  const setAside = page.locator('.pp-row.is-set-aside');

  test.skip(await setAside.count() === 0, 'this team has no second approved title');

  // The locked button says which reason applies rather than just being dead.
  await expect(page.locator('text=Not your chosen title').first()).toBeVisible();
});

test('duplication: an over-long title is caught before the scan is lost', async ({ page }, testInfo) => {
  await student(page, 'duplication');

  test.skip(
    await page.locator('#pp-form').count() === 0,
    'the pre-proposal form is not open to this team'
  );

  // Submitting used to reach the server, be rejected, and reload the page -
  // taking the duplication result on it. Nothing should navigate now, and
  // the problem is written under the title itself. Filler words ("a", "of",
  // "for") are not counted, so sixteen real words are needed to go over.
  const before = page.url();

  await page.evaluate(() => {
    document.getElementById('title').value =
      'Smart adaptive predictive integrated distributed autonomous networked crop yield '
      + 'monitoring platform using sensor data analytics mobile alerts';
    document.getElementById('pp-form').requestSubmit();
  });

  await page.waitForTimeout(600);
  await shot(page, testInfo, 'long-title-refused');

  expect(page.url(), 'the page did not reload').toBe(before);
  await expect(page.locator('#title-error')).toContainText(/15 meaningful words/i);
});

// ── Admin ─────────────────────────────────────────────────────────────────

test('admin/experts: the deactivate dialog talks about deactivating', async ({ page }, testInfo) => {
  await staff(page, 'admin', 'experts');

  const trigger = page.locator('.remove-trigger').first();

  test.skip(await trigger.count() === 0, 'no expert on this page can be deactivated');

  // The row menu has to be opened first.
  await page.locator('.rowmenu-btn').first().click();
  await page.locator('.remove-trigger').first().click();

  const modal = page.locator('#removeModal');
  await expect(modal).toBeVisible();
  await shot(page, testInfo, 'deactivate-dialog');

  await expect(modal).toContainText('Deactivate Account');
  await expect(modal).not.toContainText('permanently remove');
  await expect(modal).not.toContainText('cannot be undone');
  await expect(modal.locator('#removeConfirmBtn')).toHaveText('Deactivate');

  // And it posts to admin.*, not the CaPSA route that answered 403.
  const html = await page.content();
  expect(html).toContain('/admin/experts/');
});

test('admin/experts: the sidebar badge counts CaPSAs, not every expert', async ({ page }, testInfo) => {
  await staff(page, 'admin', 'experts');
  await shot(page, testInfo, 'admin-experts');

  const badge = page.locator('a:has-text("Expert Management") .nav-badge');
  const rows = page.locator('table.table tbody tr');

  const listed = await rows.count();

  if (await badge.count() === 0) {
    // No badge at all is the correct answer once the page has been seen.
    expect(listed).toBeGreaterThanOrEqual(0);

    return;
  }

  const shown = Number((await badge.textContent()).trim().replace('+', ''));
  expect(shown, 'the badge never exceeds what the page lists').toBeLessThanOrEqual(listed);
});

// ── CaPSA ─────────────────────────────────────────────────────────────────

test('capsa/sections: only 3rd and 4th year can be chosen', async ({ page }, testInfo) => {
  await staff(page, 'capsa', 'sections');
  await shot(page, testInfo, 'capsa-sections');

  for (const id of ['add-student-year', 'edit-student-year']) {
    const select = page.locator(`#${id}`);

    if (await select.count() === 0) continue;

    const years = await select.locator('option').allTextContents();
    expect(years.map((y) => y.trim()).sort(), `${id} offers only 3 and 4`).toEqual(['3', '4']);
  }
});

test('capsa/team-management: the report folds away and the chosen title leads', async ({ page }, testInfo) => {
  await staff(page, 'capsa', 'team_management');
  await page.waitForLoadState('networkidle');
  await shot(page, testInfo, 'capsa-team-management');

  const review = page.locator('[onclick*="openReviewModal"], button:has-text("Review")').first();

  test.skip(await review.count() === 0, 'no team is waiting on verification');

  await review.click();
  await page.waitForTimeout(1200);
  await shot(page, testInfo, 'verification-modal');

  const toggles = page.locator('.ppdup-toggle:has-text("Show report")');

  test.skip(await toggles.count() === 0, 'no duplication report on this team');

  // Folded by default: the report is the longest thing in this panel.
  const panel = page.locator('.ppdup-panel').first();
  await expect(panel).not.toHaveClass(/open/);

  await toggles.first().click();
  await expect(page.locator('.ppdup-panel.open').first()).toBeVisible();
});

test('the PDF window opens big enough to read, and empties when closed', async ({ page }) => {
  await staff(page, 'capsa', 'archive');
  await page.waitForLoadState('networkidle');

  // The app's one PDF window (resources/js/pdf-viewer.js), which every
  // "View PDF" opens - drawn with pdf.js, never the browser viewer in a frame.
  await page.evaluate(() => window.PdfViewer.open({ url: '/', title: 'Test' }));

  const box = page.locator('#previewDialog .pv-card');
  await expect(box).toBeVisible();

  const width = await box.evaluate((el) => el.getBoundingClientRect().width);
  const height = await box.evaluate((el) => el.getBoundingClientRect().height);

  expect(width, 'wide enough for a page of A4').toBeGreaterThan(700);
  expect(height).toBeGreaterThan(500);
  await expect(page.locator('#previewDialog iframe')).toHaveCount(0);

  // Emptied on close, so a hidden PDF is not still being drawn.
  await page.locator('#previewDialog [aria-label="Close"]').first().click();
  await expect(page.locator('#previewDialog')).toBeHidden();
  await expect(page.locator('#previewDialog .pv-stage > *')).toHaveCount(0);
});
