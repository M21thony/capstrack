import { expect, test } from '@playwright/test';

/**
 * Announcements (capsa/announcements): who a post reaches.
 *
 * Posts from the CaPSA page and checks each seeded student's dashboard and
 * bell. Run against the sandbox (port 8010, its own SQLite file) after
 *
 *   php artisan db:seed --class=AnnouncementsDemoSeeder
 *
 * The CaPSA covers BSIT-3A and BSIT-3B. Ana (3A) is on "PW Bug Busters",
 * Ben is her classmate, Cara is in 3B and Dan in 3C. With the sandbox down,
 * or the seed missing, the tests skip. Every post they make is deleted
 * afterwards.
 */
test.use({ baseURL: 'http://127.0.0.1:8010', viewport: { width: 1440, height: 900 } });
test.describe.configure({ mode: 'default' });

const CAPSA = 'pw-ann-capsa@cspc.edu.ph';
// Each run's titles are its own, so a bell left from an earlier run (or a
// failed one) can never pass for this one's.
const RUN = Date.now().toString(36);
const t = (title) => `PW ${title} ${RUN}`;
const student = (name) => `pw-ann-${name}@my.cspc.edu.ph`;

async function asCapsa(page) {
  const response = await page.goto(`/__dev-login-expert/capsa/announcements?email=${CAPSA}`).catch(() => null);
  test.skip(!response || response.status() >= 400, 'the sandbox on port 8010 is not running or not seeded');
  await page.evaluate(() => document.getElementById('erp-overlay')?.remove());
  await expect(page.locator('#ann-title')).toBeVisible();
}

async function post(page, { title, body, audience, sections = [], priority = 'normal' }) {
  await page.fill('#ann-title', title);
  await page.fill('#ann-message', body);
  await page.selectOption('#ann-priority', priority);
  await page.evaluate(({ audience, sections }) => setTargetAudience(audience.concat(sections.map((s) => 'section:' + s))), { audience, sections });
  const saved = page.waitForResponse((r) => r.url().includes('/api/admin/announcements') && r.request().method() === 'POST');
  await page.getByRole('button', { name: 'Post Announcement' }).click();
  expect((await saved).status()).toBe(200);
  await page.waitForLoadState('load');
}

// What a student has: the dashboard's announcements, and the bell's list.
async function seenBy(page, name, title) {
  await page.context().clearCookies();
  await page.goto(`/__dev-login-student/dashboard?email=${student(name)}`);
  await expect(page).toHaveURL(/\/student\/dashboard/);
  const onDashboard = (await page.locator('.ann-title', { hasText: title }).count()) > 0;
  const bell = await page.evaluate(async () => (await (await fetch('/notifications', { headers: { Accept: 'application/json' } })).json()).notifications);

  return { onDashboard, inBell: bell.some((n) => n.title === title || (n.message || '').includes(title)) };
}

test.afterEach(async ({ page }) => {
  await page.context().clearCookies();
  await page.goto(`/__dev-login-expert/capsa/announcements?email=${CAPSA}`).catch(() => null);
  await page.evaluate(async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    for (const card of document.querySelectorAll('.announcement-card')) {
      if (!card.querySelector('.ann-title')?.textContent.startsWith('PW ')) continue;
      await fetch('/api/admin/announcements/' + card.dataset.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    }
  }).catch(() => {});
});

test('a post to Students reaches the CaPSA\'s own sections and nobody else', async ({ page }) => {
  await asCapsa(page);
  await post(page, { title: t('Chapter 3 deadline'), body: 'Upload Chapter 3 by Friday.', audience: ['student'] });

  for (const name of ['ana', 'ben', 'cara']) {
    expect(await seenBy(page, name, t('Chapter 3 deadline')), name).toEqual({ onDashboard: true, inBell: true });
  }
  expect(await seenBy(page, 'dan', t('Chapter 3 deadline')), 'dan, outside the sections').toEqual({ onDashboard: false, inBell: false });
});

test('a picked section is the only one reached', async ({ page }) => {
  await asCapsa(page);
  await post(page, { title: t('3B room change'), body: 'BSIT-3B meets in Lab 2.', audience: ['student'], sections: ['BSIT-3B'] });

  expect(await seenBy(page, 'cara', t('3B room change'))).toEqual({ onDashboard: true, inBell: true });
  expect(await seenBy(page, 'ana', t('3B room change'))).toEqual({ onDashboard: false, inBell: false });
});

test('a team mentioned mid-sentence is the one reached', async ({ page }) => {
  await asCapsa(page);
  await post(page, { title: t('Bring the signed form'), body: 'Reminder: @PW Bug Busters please bring the signed CLF tomorrow.', audience: ['student'] });

  expect(await seenBy(page, 'ana', t('Bring the signed form'))).toEqual({ onDashboard: true, inBell: true });
  expect(await seenBy(page, 'ben', t('Bring the signed form')), 'her classmate, not on the team').toEqual({ onDashboard: false, inBell: false });
});

test('an expert-only post reaches no student', async ({ page }) => {
  await asCapsa(page);
  await post(page, { title: t('Panel briefing'), body: '@PW Bug Busters is first on Monday.', audience: ['expert'] });

  expect(await seenBy(page, 'ana', t('Panel briefing'))).toEqual({ onDashboard: false, inBell: false });
});

test('a refused post keeps the draft and says so', async ({ page }) => {
  await asCapsa(page);
  await page.route('**/api/admin/announcements', (route) => route.fulfill({ status: 422, contentType: 'application/json', body: '{"message":"The given data was invalid."}' }));

  await page.fill('#ann-title', 'PW Never sent');
  await page.fill('#ann-message', 'This one is refused.');
  await page.getByRole('button', { name: 'Post Announcement' }).click();

  await expect(page.locator('#annNotice')).toContainText('The post was not saved');
  await expect(page.locator('#ann-title')).toHaveValue('PW Never sent');
});
