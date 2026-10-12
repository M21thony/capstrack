import { expect, test } from '@playwright/test';

/**
 * The one PDF window (resources/js/pdf-viewer.js), driven with a real PDF.
 *
 * The checklist test only opens it on "/", which is not a PDF, so it proves
 * the window appears but never that a page is drawn. These feed it a
 * six-page PDF Chromium prints on the spot, then walk every control, and
 * finally open it from each real "View PDF" in the app.
 */

const SAMPLE = '/__e2e/sample.pdf';
const PAGES = 6;
let pdfBytes;

test.beforeAll(async ({ browser }) => {
  const printer = await browser.newPage();
  await printer.setContent(
    Array.from({ length: PAGES }, (_, i) =>
      `<section style="page-break-after:always;font:48px sans-serif">Page ${i + 1}</section>`).join(''),
  );
  pdfBytes = await printer.pdf({ format: 'A4' });
  await printer.close();
});

const staff = async (page, role, at = '') => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`/__dev-login-expert/${role}/${at}`);
  await expect(page, `signed in as ${role}`).not.toHaveURL(/\/login/);
};

/** The archive includes the window, so it hosts the tests that call it directly. */
const host = async (page) => {
  await page.route(`**${SAMPLE}`, (r) => r.fulfill({ body: pdfBytes, contentType: 'application/pdf' }));
  await staff(page, 'capsa', 'archive');
  await page.waitForFunction(() => window.PdfViewer);
};

const openSample = (page, extra = {}) =>
  page.evaluate(([url, extra]) => window.PdfViewer.open({ url, title: 'Sample paper', meta: 'Team Test · BSIT-3A', ...extra }), [SAMPLE, extra]);

const win = (page) => page.locator('#previewDialog');
const current = (page) => win(page).locator('.pv-pgin');

/** Every page drawn on screen is a canvas with ink on it, not a blank box. */
const pageHasInk = (page, n) =>
  win(page).locator(`.pv-page[data-n="${n}"] canvas`).evaluate((c) => {
    const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
    for (let i = 0; i < d.length; i += 4) {
      if (d[i] < 128) {
        return true;
      }
    }
    return false;
  });

test('a real PDF is drawn page by page, with no iframe', async ({ page }, testInfo) => {
  await host(page);
  await openSample(page);

  await expect(win(page)).toBeVisible();
  await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);
  await expect(win(page).locator('.pv-page[data-n="1"] canvas')).toBeVisible();
  expect(await pageHasInk(page, 1), 'page 1 has text on it').toBe(true);

  await expect(win(page).locator('.pv-title')).toHaveText('Sample paper');
  await expect(win(page).locator('.pv-meta')).toHaveText('Team Test · BSIT-3A');
  await expect(win(page).locator('.pv-file')).toHaveText(/sample\.pdf · \d+ KB/);
  await expect(win(page).locator('.pv-pager .pv-total')).toHaveText(String(PAGES));
  await expect(win(page).locator('.pv-thumb')).toHaveCount(PAGES);
  await expect(win(page).locator('.pv-thumb').first().locator('canvas')).toBeVisible();
  await expect(win(page).locator('iframe')).toHaveCount(0);

  const card = await win(page).locator('.pv-card').boundingBox();
  expect(card.width, 'wide enough for a page of A4').toBeGreaterThan(700);
  expect(card.height).toBeGreaterThan(500);

  await testInfo.attach('desktop', { body: await page.screenshot(), contentType: 'image/png' });
});

test('the page controls move through the paper', async ({ page }) => {
  await host(page);
  await openSample(page);
  await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);

  const prev = win(page).locator('.pv-pager [data-pv="prev"]');
  const next = win(page).locator('.pv-pager [data-pv="next"]');
  await expect(current(page)).toHaveValue('1');
  await expect(prev).toBeDisabled();

  await next.click();
  await expect(current(page)).toHaveValue('2');
  await expect(prev).toBeEnabled();

  await current(page).fill('5');
  await current(page).press('Enter');
  await expect(current(page)).toHaveValue('5');
  await expect.poll(() => pageHasInk(page, 5), { message: 'page 5 drawn once reached' }).toBe(true);

  await win(page).locator('.pv-thumb[data-n="3"]').click();
  await expect(current(page)).toHaveValue('3');
  await expect(win(page).locator('.pv-thumb[data-n="3"]')).toHaveAttribute('aria-current', 'page');

  // A page number past the end lands on the last page.
  await current(page).fill('99');
  await current(page).press('Enter');
  await expect(current(page)).toHaveValue(String(PAGES));
  await expect(next).toBeDisabled();
});

test('arrow keys, Home and End turn pages; + and - zoom', async ({ page }) => {
  await host(page);
  await openSample(page);
  await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);
  const stage = win(page).locator('.pv-stage');
  await stage.focus();

  await page.keyboard.press('ArrowRight');
  await expect(current(page)).toHaveValue('2');
  await page.keyboard.press('End');
  await expect(current(page)).toHaveValue(String(PAGES));
  await page.keyboard.press('Home');
  await expect(current(page)).toHaveValue('1');

  const label = win(page).locator('.pv-zl');
  const before = await label.textContent();
  await page.keyboard.press('+');
  await expect(label).not.toHaveText(before);
  await page.keyboard.press('-');
  await expect(label).toHaveText(before);
});

test('zoom grows the page, Fit width brings it back', async ({ page }) => {
  await host(page);
  await openSample(page);
  await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);

  const first = win(page).locator('.pv-page[data-n="1"]');
  const fitted = (await first.boundingBox()).width;
  const fittedLabel = await win(page).locator('.pv-zl').textContent();

  await win(page).locator('.pv-zoom [data-pv="in"]').click();
  await expect.poll(async () => (await first.boundingBox()).width).toBeGreaterThan(fitted * 1.2);
  // Redrawn sharp at the new size, not a stretched picture of the old one.
  await expect.poll(() => first.locator('canvas').evaluate((c) => c.width)).toBeGreaterThan(fitted * 1.2);

  await win(page).locator('.pv-zoom [data-pv="out"]').click();
  await win(page).locator('.pv-zoom [data-pv="out"]').click();
  await expect.poll(async () => (await first.boundingBox()).width).toBeLessThan(fitted);

  await win(page).locator('[data-pv="fit"]').click();
  await expect(win(page).locator('.pv-zl')).toHaveText(fittedLabel);
  expect(Math.abs((await first.boundingBox()).width - fitted)).toBeLessThan(2);
});

test('Download points at the file, under its own name', async ({ page }) => {
  await host(page);
  await openSample(page, { fileName: 'Chapter 1.pdf' });

  const dl = win(page).locator('.pv-dl');
  await expect(dl).toBeVisible();
  await expect(dl).toHaveAttribute('href', SAMPLE);
  await expect(dl).toHaveAttribute('download', 'Chapter 1.pdf');

  await openSample(page, { download: false });
  await expect(dl).toBeHidden();
});

test('Esc, the close button and the backdrop all close it and stop the drawing', async ({ page }) => {
  await host(page);
  const scrollBefore = await page.evaluate(() => document.documentElement.style.overflow);

  for (const how of ['Escape', 'button', 'backdrop']) {
    await openSample(page);
    await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);
    expect(await page.evaluate(() => document.documentElement.style.overflow), 'page behind stops scrolling').toBe('hidden');

    if (how === 'Escape') {
      await page.keyboard.press('Escape');
    } else if (how === 'button') {
      await win(page).locator('.pv-x').click();
    } else {
      await page.mouse.click(5, 5);
    }

    await expect(win(page), `closed by ${how}`).toBeHidden();
    await expect(win(page).locator('.pv-page')).toHaveCount(0);
    expect(await page.evaluate(() => window.PdfViewer.isOpen())).toBe(false);
    expect(await page.evaluate(() => document.documentElement.style.overflow)).toBe(scrollBefore);
  }
});

test('a PDF that cannot be fetched says so instead of spinning', async ({ page }) => {
  await host(page);
  await page.route('**/__e2e/missing.pdf', (r) => r.fulfill({ status: 404, body: 'nope' }));
  await page.evaluate(() => window.PdfViewer.open({ url: '/__e2e/missing.pdf', title: 'Missing' }));
  await expect(win(page).locator('.pv-msg')).toHaveText(/could not be loaded/);
  await expect(win(page).locator('.pv-spin')).toHaveCount(0);

  // A file that is not a PDF at all.
  await page.evaluate(() => window.PdfViewer.open({ file: new File(['not a pdf'], 'broken.pdf', { type: 'application/pdf' }), title: 'Broken' }));
  await expect(win(page).locator('.pv-msg')).toHaveText(/cannot be shown/);
});

test('a file on this device is shown before it is sent: a PDF or a picture', async ({ page }) => {
  await host(page);

  await page.evaluate(async (url) => {
    const blob = await (await fetch(url)).blob();
    window.PdfViewer.open({ file: new File([blob], 'draft.pdf', { type: 'application/pdf' }), title: 'draft.pdf', download: false });
  }, SAMPLE);
  await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);
  expect(await pageHasInk(page, 1)).toBe(true);

  await page.evaluate(async () => {
    const c = Object.assign(document.createElement('canvas'), { width: 200, height: 100 });
    c.getContext('2d').fillRect(0, 0, 200, 100);
    const png = await new Promise((done) => c.toBlob(done, 'image/png'));
    window.PdfViewer.open({ file: new File([png], 'scan.png', { type: 'image/png' }), title: 'scan.png' });
  });
  await expect(win(page).locator('.pv-stage img')).toBeVisible();
  await expect(win(page).locator('.pv-pager')).toBeHidden();
  await expect(win(page).locator('.pv-thumbs')).toBeHidden();
});

test('a link with data-pdf-view opens the window; Ctrl-click is left to the browser', async ({ page }) => {
  await host(page);
  await page.evaluate((url) => {
    const a = Object.assign(document.createElement('a'), { href: url, id: 'e2e-link', textContent: 'Chapter 2' });
    a.dataset.pdfView = url;
    a.dataset.pdfMeta = 'from a link';
    document.body.prepend(a);
  }, SAMPLE);

  await page.locator('#e2e-link').click();
  await expect(win(page)).toBeVisible();
  await expect(win(page).locator('.pv-title')).toHaveText('Chapter 2');
  await expect(win(page).locator('.pv-meta')).toHaveText('from a link');
  await page.keyboard.press('Escape');

  const tab = page.context().waitForEvent('page');
  await page.locator('#e2e-link').click({ modifiers: ['Control'] });
  await (await tab).close();
  expect(await page.evaluate(() => window.PdfViewer.isOpen()), 'Ctrl-click did not open the window').toBe(false);
});

test('on a phone it fills the screen with the floating page pill', async ({ page }, testInfo) => {
  await host(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await openSample(page);
  await expect(win(page).locator('.pv-page')).toHaveCount(PAGES);

  const card = await win(page).locator('.pv-card').boundingBox();
  expect(card.width).toBeGreaterThan(380);
  expect(card.height).toBeGreaterThan(800);

  const pill = win(page).locator('.pv-pill');
  await expect(pill).toBeVisible();
  await pill.locator('[data-pv="next"]').click();
  await expect(pill.locator('.pv-cur')).toHaveText('2');

  const pageWidth = (await win(page).locator('.pv-page[data-n="1"]').boundingBox()).width;
  expect(pageWidth, 'the page fits the phone, no sideways scroll').toBeLessThanOrEqual(390);

  await testInfo.attach('phone', { body: await page.screenshot(), contentType: 'image/png' });
});

// ── the real "View PDF" buttons ───────────────────────────────────────────

test('archive: View PDF on a capstone opens its file in the window', async ({ page }) => {
  await staff(page, 'capsa', 'archive');
  // ABSTRACTS is the page's own list of capstones; only some carry a file.
  const id = await page.evaluate(() => Object.keys(ABSTRACTS).find((k) => ABSTRACTS[k].pdf));
  test.skip(!id, 'no archived capstone has a PDF');

  await page.evaluate((id) => openAbstract(id), id);
  await page.locator('#abs-pdf').click();
  await expect(win(page)).toBeVisible();
  await expect(win(page).locator('.pv-page').first().locator('canvas')).toBeVisible({ timeout: 20000 });
  expect(page.context().pages()).toHaveLength(1);
});

test('reports: Preview PDF draws the report the server makes', async ({ page }) => {
  await staff(page, 'capsa', 'reports');
  // Each report row opens through its own GET form.
  const report = await page.locator('form[id^="f-"]').first().getAttribute('action');
  test.skip(!report, 'no reports on the reports page');
  await page.goto(report);

  await page.locator('#preview-btn').click();
  await expect(win(page)).toBeVisible();
  await expect(win(page).locator('.pv-page').first().locator('canvas')).toBeVisible({ timeout: 30000 });
  expect(await pageHasInk(page, 1), 'the report page has text on it').toBe(true);
  await expect(win(page).locator('.pv-dl')).toHaveAttribute('download', /\.pdf$/);
});

test('team management: View original PDF on a title page', async ({ page }) => {
  await staff(page, 'capsa', 'team_management');
  const teams = await page.locator('a[href*="/capsa/team-management/"]').evaluateAll((as) =>
    [...new Set(as.map((a) => a.getAttribute('href')).filter((h) => /\/capsa\/team-management\/\d+$/.test(h)))]);
  test.skip(!teams.length, 'no teams on Team Management');

  let found = false;
  for (const team of teams.slice(0, 15)) {
    await page.goto(`${team}/pre-proposals`);
    const title = page.locator('a[href*="/pre-proposals/"]').first();
    if (!(await title.count())) {
      continue;
    }
    await title.click();
    if (await page.locator('#pdf-open').count()) {
      found = true;
      break;
    }
  }
  test.skip(!found, 'no team has a pre-proposal PDF');

  await page.locator('#pdf-open').click();
  await expect(win(page)).toBeVisible();
  await expect(win(page).locator('.pv-page').first().locator('canvas')).toBeVisible({ timeout: 20000 });
});
