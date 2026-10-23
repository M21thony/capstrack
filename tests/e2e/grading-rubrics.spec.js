import { expect, test } from '@playwright/test';

/**
 * Grading Rubrics (capsa/rubric_management), five ways: the stage tiles filter
 * the list, the New Rubric dialog, building criteria up to 100, saving and
 * reopening, and archive/restore.
 *
 * These save and archive rubrics, so they run against the sandbox copy
 * (capstrack-sandbox in .claude/launch.json: port 8010, its own SQLite file),
 * never the real database on 8000. Start the sandbox first; with it down the
 * tests skip. It is PHP's one-request-at-a-time server, so they run in order;
 * repeating them (--repeat-each) needs --workers=1 too, or the copies race on
 * the tile counts.
 */
test.use({ baseURL: 'http://127.0.0.1:8010', viewport: { width: 1440, height: 900 } });
test.describe.configure({ mode: 'default' });

// Every rubric these tests made is archived afterwards - the page has no
// delete - even when a test fails partway, so repeat runs leave the sandbox's
// live lists as they found them.
test.afterEach(async ({ page }) => {
  await page.evaluate(async () => {
    if (typeof loadRubrics !== 'function') return;
    await loadRubrics();
    for (const r of rubrics.filter((r) => r.name.startsWith('PW ') && !r.isArchived)) {
      await fetch('/api/admin/grading-rubrics/' + r.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    }
  }).catch(() => {});
});

async function openPage(page) {
  const response = await page.goto('/__dev-login-expert/capsa/rubric_management').catch(() => null);
  test.skip(!response || response.status() === 404, 'the sandbox on port 8010 is not running');
  // The "evaluations needed" reminder can cover the page; it is not under test.
  await page.evaluate(() => document.getElementById('erp-overlay')?.remove());
  await expect(page.locator('#rb-tiles .mk-tile')).toHaveCount(4);
}

// A two-criterion rubric made straight through the API, for the tests that
// are about the list rather than the editor.
async function createRubric(page, name, type) {
  return page.evaluate(async ({ name, type }) => {
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' };
    const made = await (await fetch('/api/admin/grading-rubrics', {
      method: 'POST', headers,
      body: JSON.stringify({ rubric_name: name, rubric_type: type, program: null, description: '', total_points: 100 }),
    })).json();
    const id = made.data.rubric_id;
    const levels = [['Excellent', 50], ['Good', 37.5], ['Fair', 25], ['Poor', 12.5]].map(([label, score]) => ({ label, score, desc: label + ' work' }));
    await fetch(`/api/admin/grading-rubrics/${id}/criteria`, {
      method: 'PUT', headers,
      body: JSON.stringify({ criteria: ['Design', 'Delivery'].map((n) => ({
        criterion_name: n, category: 'Presentation', category_max_points: null, max_points: 50, score_levels: JSON.stringify(levels), sort_order: 0,
      })) }),
    });
    await loadRubrics();
  }, { name, type });
}

async function startBlank(page, name, type) {
  await page.getByRole('button', { name: 'New Rubric', exact: true }).click();
  await page.locator('#rb-name').fill(name);
  await page.locator(`.mk-stages button[data-type="${type}"]`).click();
  await page.getByRole('button', { name: 'Start blank' }).click();
  await expect(page.locator('#rb-editor-view')).toBeVisible();
}

const tileFor = (page, type) => page.locator(`#rb-tiles .mk-tile[onclick*="'${type}'"]`);
const countIs = (n) => new RegExp(`^${n}\\D`);

test('1. a stage tile filters the list, and clicking it again clears the filter', async ({ page }) => {
  await openPage(page);
  const name = `PW Filter ${Date.now()}`;
  await createRubric(page, name, 'pre_proposal');
  const row = page.locator('#rb-list tr', { hasText: name });
  await expect(row).toHaveCount(1);

  await tileFor(page, 'title_defense').click();
  await expect(tileFor(page, 'title_defense')).toHaveClass(/\bon\b/);
  await expect(page.locator('#rb-list-title')).toHaveText(await tileFor(page, 'title_defense').locator('b').textContent());
  await expect(row).toHaveCount(0);
  const label = await page.evaluate(() => TYPE_LABEL.title_defense);
  for (const stage of await page.locator('#rb-list .mk-stage').allTextContents()) expect(stage).toBe(label);

  await tileFor(page, 'pre_proposal').click();
  await expect(row).toHaveCount(1);
  await tileFor(page, 'pre_proposal').click();
  await expect(page.locator('#rb-list-title')).toHaveText('All rubrics');
  await expect(page.locator('#rb-tiles .mk-tile.on')).toHaveCount(0);
  await expect(row).toHaveCount(1);

  await page.locator('#rb-search').fill(name);
  await expect(page.locator('#rb-list tr')).toHaveCount(1);
  await expect(page.locator('#rb-count')).toHaveText('1 rubric');
});

test('2. New Rubric opens a centred dialog with a big AI box and a typed criteria count', async ({ page }) => {
  await openPage(page);
  await page.getByRole('button', { name: 'New Rubric', exact: true }).click();
  const dialog = page.locator('#rb-dialog');
  await expect(dialog).toBeVisible();
  await page.waitForTimeout(300);

  const geo = await page.evaluate(() => {
    const r = document.getElementById('rb-dialog').getBoundingClientRect();
    return { dx: Math.abs(r.left + r.width / 2 - innerWidth / 2), dy: Math.abs(r.top + r.height / 2 - innerHeight / 2), aiBox: document.getElementById('rb-ai-topic').offsetHeight };
  });
  expect(geo.dx).toBeLessThanOrEqual(2);
  expect(geo.dy).toBeLessThanOrEqual(2);
  expect(geo.aiBox).toBeGreaterThanOrEqual(90);

  const count = page.locator('#rb-ai-count');
  await expect(count).toHaveAttribute('type', 'number');
  await expect(count).toHaveAttribute('min', '3');
  await expect(count).toHaveAttribute('max', '15');
  await count.fill('12');
  await expect(count).toHaveValue('12');

  // A name and a stage come first.
  await page.getByRole('button', { name: 'Start blank' }).click();
  await expect(dialog).toBeVisible();
  await page.locator('#rb-name').fill('PW Dialog Rubric');
  await page.getByRole('button', { name: 'Start blank' }).click();
  await expect(dialog).toBeVisible();
  await expect(page.locator('#rb-editor-view')).toBeHidden();

  await page.locator('.mk-stages button[data-type="pre_oral_defense"]').click();
  await expect(page.locator('.mk-stages button.on')).toHaveCount(1);
  await page.getByRole('button', { name: 'Start blank' }).click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('#rb-editor-view')).toBeVisible();
  await expect(page.locator('.page-title .mk-crumb')).toContainText('PW Dialog Rubric');
  await expect(page.locator('#rb-matrix')).toContainText('No criteria yet');

  // The breadcrumb is the way back.
  await page.locator('.mk-crumb a').click();
  await expect(page.locator('#rb-list-view')).toBeVisible();
  await expect(page.locator('.page-title')).toHaveText('Grading Rubrics');
});

test('3. criteria go into the category asked for, and scores fit to 100', async ({ page }) => {
  await openPage(page);
  await startBlank(page, 'PW Editor Rubric', 'title_defense');

  await page.getByRole('button', { name: 'Add category' }).click();
  const bands = page.locator('#rb-matrix .mk-cat-in');
  await expect(bands).toHaveCount(1);
  await expect(bands.first()).toHaveValue('New category');
  await bands.first().fill('Documentation');
  await bands.first().press('Tab');
  await page.getByRole('button', { name: 'Add criterion to Documentation' }).click();
  await page.getByRole('button', { name: 'Add category' }).click();
  await expect(bands).toHaveCount(2);
  await expect(page.locator('#rb-matrix tr[id^="crc-"]')).toHaveCount(3);
  expect(await page.evaluate(() => draftCriteria.map((c) => c.category))).toEqual(['Documentation', 'Documentation', 'New category']);

  const score = async (i, value) => {
    await page.locator(`#sc-${i}-0`).fill(value);
    await page.locator(`#sc-${i}-0`).press('Tab');
  };
  const total = page.locator('#mk-total .v b');
  const hint = page.locator('#mk-total .hint');

  // Excellent carries the lower levels down with it.
  await score(0, '40');
  await expect(total).toHaveText('40');
  await expect(hint).toHaveText('60 points still unassigned.');
  for (const [li, v] of [[1, '30'], [2, '20'], [3, '10']]) await expect(page.locator(`#sc-0-${li}`)).toHaveValue(v);

  await score(1, '35');
  await score(2, '25');
  await expect(total).toHaveText('100');
  await expect(hint).toHaveText('Adds up to 100.');

  // Released, criterion 2 is the one that gives way when criterion 3 grows;
  // criterion 1, still set by hand, keeps its 40.
  await page.locator('#crc-1 .rb-pin').click();
  await expect(page.locator('#crc-1')).not.toHaveClass(/pinned/);
  await score(2, '45');
  await expect(page.locator('#sc-0-0')).toHaveValue('40');
  for (const [li, v] of [[0, '15'], [1, '11.25'], [2, '7.5'], [3, '3.75']]) await expect(page.locator(`#sc-1-${li}`)).toHaveValue(v);
  await expect(total).toHaveText('100');
  await expect(page.locator('#crc-0')).toHaveClass(/pinned/);
  await expect(page.locator('#crc-2')).toHaveClass(/pinned/);
});

test('4. a saved rubric shows in the list with its points, and Open brings it back', async ({ page }) => {
  await openPage(page);
  const tile = tileFor(page, 'final_oral_defense');
  const before = parseInt(await tile.locator('.n').textContent(), 10);
  const name = `PW Saved ${Date.now()}`;
  await startBlank(page, name, 'final_oral_defense');

  await page.getByRole('button', { name: 'Add category' }).click();
  await page.locator('.mk-cat-in').fill('Defense');
  await page.locator('.mk-cat-in').press('Tab');
  await page.getByRole('button', { name: 'Add criterion to Defense' }).click();
  for (const [i, criterion, points] of [[0, 'Answers questions', '60'], [1, 'Slides', '40']]) {
    await page.locator(`#crc-${i} .mk-crit-in`).fill(criterion);
    const descs = page.locator(`#crc-${i} textarea`);
    for (let li = 0; li < 4; li++) await descs.nth(li).fill(`Level ${li + 1}`);
    await page.locator(`#sc-${i}-0`).fill(points);
    await page.locator(`#sc-${i}-0`).press('Tab');
  }
  await expect(page.locator('#mk-total .hint')).toHaveText('Adds up to 100.');

  await page.locator('#form-save-btn').click();
  await expect(page.locator('#rb-list-view')).toBeVisible();
  const row = page.locator('#rb-list tr', { hasText: name });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('100 / 100');
  await expect(row.locator('td').nth(3)).toContainText('1 category');
  await expect(tile.locator('.n')).toHaveText(countIs(before + 1));

  await row.getByRole('button', { name: 'Open' }).click();
  await expect(page.locator('#rb-editor-view')).toBeVisible();
  await expect(page.locator('#form-save-label')).toHaveText('Save changes');
  await expect(page.locator('.mk-cat-in')).toHaveValue('Defense');
  await expect(page.locator('#crc-0 .mk-crit-in')).toHaveValue('Answers questions');
  await expect(page.locator('#crc-1 .mk-crit-in')).toHaveValue('Slides');
  expect(Number(await page.locator('#sc-0-0').inputValue())).toBe(60);
  expect(Number(await page.locator('#sc-0-1').inputValue())).toBe(45);
  expect(Number(await page.locator('#sc-1-0').inputValue())).toBe(40);
  await expect(page.locator('#mk-total .hint')).toHaveText('Adds up to 100.');

  await page.locator('#rb-editor-view').getByRole('button', { name: 'Cancel' }).click();
  await expect(page.locator('#rb-list-view')).toBeVisible();
});

test('5. Archive hides a rubric, Show archived lists it, and Restore brings it back', async ({ page }) => {
  await openPage(page);
  const name = `PW Archive ${Date.now()}`;
  await createRubric(page, name, 'title_defense');
  const tile = tileFor(page, 'title_defense');
  const before = parseInt(await tile.locator('.n').textContent(), 10);
  const row = page.locator('#rb-list tr', { hasText: name });

  await row.getByRole('button', { name: 'Archive' }).click();
  await expect(page.locator('#del-modal')).toHaveClass(/show/);
  await expect(page.locator('#del-modal-sub')).toContainText(name);
  await page.locator('#del-confirm-btn').click();
  await expect(page.locator('#del-modal')).not.toHaveClass(/show/);
  await expect(row).toHaveCount(0);
  await expect(tile.locator('.n')).toHaveText(countIs(before - 1));

  await page.locator('#rb-show-archived').check();
  await expect(row).toHaveCount(1);
  await row.getByRole('button', { name: 'Restore' }).click();
  await expect(page.locator('#del-modal-title')).toHaveText('Restore Rubric?');
  await page.locator('#del-confirm-btn').click();
  await expect(row.getByRole('button', { name: 'Archive' })).toBeVisible();
  await expect(tile.locator('.n')).toHaveText(countIs(before));
});
