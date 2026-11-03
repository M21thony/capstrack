// End to end across roles: a student sends from the Experts page, then the
// expert or CaPSA it went to signs in and sees it, and the answer comes back.
// These SEND - run them only against the no-mail server, which logs every
// email instead (preview "capstrack-e2e-nomail"):
//   PW_BASE_URL=http://127.0.0.1:8002 npx playwright test experts-end-to-end
// Every row they add is deleted after, and the two flags they flip are put back.
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.skip(!process.env.PW_BASE_URL, 'sends - point PW_BASE_URL at the no-mail server');
test.describe.configure({ mode: 'serial' });

// Runs PHP in the app; whatever it echoes after "@@" comes back as JSON.
const php = (code) => {
  const out = execFileSync('php', ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' });
  const at = out.lastIndexOf('@@');
  return at < 0 ? null : JSON.parse(out.slice(at + 2).trim());
};

// Student 131 leads team 111 "Demo Pixel Pioneers": adviser Marites Castillo
// (121), section BSIT-3A, whose CaPSA (91) is the dev "capsa" login.
// Student 24 leads team 119, unconfirmed - confirmed for the apply test only.
const TEAM = 'Demo Pixel Pioneers';
// Children first, so the clean-up never leaves one pointing at nothing.
const MODELS = ['Notification', 'ActivityLog', 'ExpertEvaluation', 'EvaluationTemplateQuestion', 'EvaluationTemplate', 'ExpertRemovalRequest', 'TeamExpertAssignment', 'ConsultationBooking'];
let before;

test.beforeAll(() => {
  before = php(`$o = [];
    ${MODELS.map((m) => `$o['${m}'] = (int) App\\Models\\${m}::max((new App\\Models\\${m})->getKeyName());`).join('\n')}
    $o['confirmed'] = App\\Models\\Team::find(119)->getRawOriginal('is_confirmed');
    $d = App\\Models\\DefenseSchedule::find(44); $o['outcome'] = $d->outcome; $o['recorded'] = $d->getRawOriginal('outcome_recorded_at');
    echo '@@'.json_encode($o);`);
});

test.afterAll(() => {
  if (!before) { return; }
  php(`${MODELS.map((m) => `App\\Models\\${m}::where((new App\\Models\\${m})->getKeyName(), '>', ${before[m]})->delete();`).join('\n')}
    App\\Models\\Team::whereKey(119)->update(['is_confirmed' => ${JSON.stringify(before.confirmed)}]);
    App\\Models\\DefenseSchedule::whereKey(44)->update(['outcome' => ${JSON.stringify(before.outcome)}, 'outcome_recorded_at' => ${JSON.stringify(before.recorded)}]);
    echo '@@1';`);
});

const tidy = async (page) => {
  // The first-visit tour sits over everything; it is not what is under test.
  await page.evaluate(() => { document.querySelectorAll('#portal-tour, .gt, .gt-shield').forEach((el) => el.remove()); document.body.classList.remove('tour-open'); });
  await expect(page.locator('#csk')).toHaveCount(0);
};
const student = async (page, as = '131', tab = '') => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(`/__dev-login-student/experts?as=${as}`);
  await expect(page, 'signed in as a student').not.toHaveURL(/\/login/);
  if (tab) { await page.goto(`/student/experts?tab=${tab}`); }
  await tidy(page);
};
// The other side gets its own browser, so the student's sign-in is not shared.
const staff = async (browser, login, at) => {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`/__dev-login-expert/${login}`);
  await expect(page, 'signed in as staff').not.toHaveURL(/\/login/);
  await page.goto(at);
  await tidy(page);
  return page;
};
const again = async (page) => { await page.reload(); await tidy(page); };
// A team without an approved proposal attaches one; any small PDF will do.
const attachPdf = async (dlg) => {
  const file = dlg.locator('input[type="file"]');
  if (await file.count()) {
    await file.setInputFiles({ name: 'pre-proposal.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n') });
  }
};
const shot = async (page, testInfo, name) => {
  const path = testInfo.outputPath(`${name}.png`);
  await page.screenshot({ path });
  return testInfo.attach(name, { path, contentType: 'image/png' });
};

test('a booking reaches the adviser, and the cancel they approve reaches the team', async ({ page, browser }, testInfo) => {
  await student(page);
  await page.locator('[data-xh="schedule"][data-role="adviser"]').click();
  // The last open day, so it is far enough ahead to be cancelled.
  await page.locator('dialog[open] [data-xh="date"]:not([disabled])').last().click();
  await page.locator('dialog[open] [data-xh="slot"]').first().click();
  const dlg = page.locator('dialog[open]');
  await dlg.locator('#xh-deliv-dd > summary').click();
  await dlg.locator('.xh-dd-item', { hasText: 'Chapters 1–3, together' }).click();
  await dlg.locator('#xh-deliv-dd > summary').click();
  await dlg.locator('#xh-book-send').click();
  await expect.poll(() => php(`echo '@@'.(int) App\\Models\\ConsultationBooking::max('booking_id');`), { message: 'a booking was saved' }).toBeGreaterThan(before.ConsultationBooking);
  const id = php(`echo '@@'.(int) App\\Models\\ConsultationBooking::max('booking_id');`);
  await again(page);
  await expect(page.locator(`#xh-panel-book [data-xh="session"][data-id="${id}"]`).first()).toBeVisible();
  await shot(page, testInfo, '1a-student-booked');

  const adviser = await staff(browser, '121/schedule', '/instructor/schedule');
  const row = adviser.locator('#upcoming-list .sc-sess', { hasText: TEAM });
  await expect(row, 'the adviser sees the team booked').toBeVisible();
  await expect(row).toContainText('Chapters 1–3');
  await shot(adviser, testInfo, '1b-adviser-sees-booking');

  // The team asks to cancel; it stays booked until the adviser answers.
  await page.locator(`[data-xh="session"][data-id="${id}"]`).first().click();
  await page.locator('dialog[open] [data-xh="cancel-open"]').click();
  await page.locator('#xh-cancel-reason').fill('Our chapter 3 draft is not ready yet.');
  await page.locator('[data-xh="cancel-send"]').click();
  await expect(page.getByText('Cancellation sent').first()).toBeVisible();

  await again(adviser);
  const req = adviser.locator('#sc-requests .sc-req', { hasText: TEAM });
  await expect(req, 'the adviser sees the cancel request').toBeVisible();
  await expect(req).toContainText('Our chapter 3 draft is not ready yet.');
  await shot(adviser, testInfo, '1c-adviser-sees-cancel-request');
  await req.locator('.sc-review').click();
  await adviser.locator('dialog[open]').getByRole('button', { name: 'Approve' }).click();
  // Approving asks once more before the session is gone.
  await adviser.locator('dialog[open]', { hasText: 'Approve this cancellation?' }).getByRole('button', { name: 'Approve' }).click();
  await expect.poll(() => php(`echo '@@'.json_encode(App\\Models\\ConsultationBooking::find(${id})->booking_status);`)).toBe('cancelled');
  await again(adviser);
  await expect(adviser.locator('#upcoming-list .sc-sess', { hasText: TEAM })).toHaveCount(0);
  await shot(adviser, testInfo, '1d-adviser-after-approving');

  await again(page);
  await expect(page.locator(`#xh-panel-book [data-xh="session"][data-id="${id}"]`), 'gone for the team too').toHaveCount(0);
  await shot(page, testInfo, '1e-student-after-approved');
  await adviser.context().close();
});

test('a change request reaches the section CaPSA, and taking it back clears it', async ({ page, browser }, testInfo) => {
  await student(page, '131', 'find');
  const change = page.locator('[data-xh="change"]').first();
  const name = await change.getAttribute('data-name');
  await change.click();
  const dlg = page.locator('dialog[open]');
  await dlg.locator('#xh-change-reason').fill('Our adviser has moved to another campus this term, so we need someone who can meet us weekly.');
  await dlg.locator('#xh-change-q').fill('an');
  await page.keyboard.press('Enter');
  await dlg.locator('#xh-change-msg').fill('We are building a capstone tracker and would value your guidance this term.');
  await attachPdf(dlg);
  await dlg.locator('#xh-change-send').click();
  await expect(page.locator('[data-xh="takeback"]')).toBeVisible();
  await shot(page, testInfo, '2a-student-asked-change');

  const capsa = await staff(browser, 'capsa/team_management', '/capsa/team-management?tab=leave_requests');
  const line = capsa.getByText(new RegExp(`asks to remove ${name} as \\w+ of ${TEAM}`));
  await expect(line, 'the CaPSA sees the change request').toBeVisible();
  await shot(capsa, testInfo, '2b-capsa-sees-change-request');

  // The team takes it back; it leaves the CaPSA's list.
  await page.locator('[data-xh="takeback"]').click();
  await page.locator('dialog[open] [data-xh="takeback-send"]').click();
  await expect(page.locator('[data-xh="takeback"]')).toHaveCount(0);
  await again(capsa);
  await expect(line).toHaveCount(0);
  await shot(capsa, testInfo, '2c-capsa-after-taken-back');
  await capsa.context().close();
});

test('an application reaches the expert, and withdrawing it clears it', async ({ page, browser }, testInfo) => {
  php(`App\\Models\\Team::whereKey(119)->update(['is_confirmed' => 1]); echo '@@1';`);
  const team = php(`echo '@@'.json_encode(App\\Models\\Team::find(119)->team_name);`);
  await student(page, '24', 'find');
  await expect(page.locator('[data-xh="apply"]').first(), 'team 119 can apply').toBeVisible();
  // The card counts all slots, the server the team's program's - take one with room in both.
  const ids = await page.locator('[data-xh="apply"]:visible').evaluateAll((els) => els.map((el) => Number(el.dataset.id)));
  const expertId = php(`$a = app(App\\Support\\ExpertApplications::class);
    echo '@@'.json_encode(collect(${JSON.stringify(ids)})->first(fn ($id) => ! $a->fullAt(App\\Models\\CapstoneExpert::find($id), 'BSIT')));`);
  expect(expertId, 'an expert with a BSIT slot is on the first page').toBeTruthy();
  const apply = page.locator(`[data-xh="apply"][data-id="${expertId}"]`);
  await apply.click();
  const dlg = page.locator('dialog[open]');
  const role = dlg.locator('#xh-f-role input[type="radio"]:not([disabled])');
  if (await role.count()) { await role.first().check(); }
  await dlg.locator('#xh-apply-msg').fill('We are building a capstone tracker and would value your guidance on the system design chapters.');
  await attachPdf(dlg);
  await dlg.locator('[data-xh="apply-review"]').click();
  const sent = page.waitForResponse((r) => r.url().includes('/assignments') && r.request().method() === 'POST');
  await page.locator('dialog[open] [data-xh="apply-send"]').click();
  const body = await (await sent).json();
  expect(body.success, body.message).toBe(true);
  await expect.poll(() => php(`echo '@@'.(int) App\\Models\\TeamExpertAssignment::where('team_id', 119)->where('expert_id', ${Number(expertId)})->max('assignment_id');`), { message: 'an application was saved' }).toBeGreaterThan(before.TeamExpertAssignment);
  const id = php(`echo '@@'.(int) App\\Models\\TeamExpertAssignment::where('team_id', 119)->max('assignment_id');`);
  // The page reloads itself onto Find experts after sending.
  await page.waitForEvent('load');
  await tidy(page);
  await shot(page, testInfo, '3a-student-applied');

  const expert = await staff(browser, `${expertId}/assignments`, '/instructor/assignments');
  const appRow = expert.locator('tr:visible', { hasText: team });
  await expect(appRow, 'the expert sees the application').toBeVisible();
  await shot(expert, testInfo, '3b-expert-sees-application');

  await page.locator(`[data-xh="app"][data-id="${id}"]:visible`).first().click();
  await page.locator('dialog[open] [data-xh="app-withdraw"]').click();
  await page.locator('dialog[open] [data-xh="app-withdraw-send"]').click();
  await expect.poll(() => php(`echo '@@'.json_encode(App\\Models\\TeamExpertAssignment::find(${id})?->status);`)).not.toBe('pending');
  await again(expert);
  await expect(appRow).toHaveCount(0);
  await shot(expert, testInfo, '3c-expert-after-withdrawn');
  await expert.context().close();
});

test('evaluations the team sends show up for the CaPSA', async ({ page, browser }, testInfo) => {
  // Owed only after a passed defense, with the section CaPSA's form set up
  // before it was decided - both made here and undone after.
  php(`$t = App\\Models\\EvaluationTemplate::create(['capsa_id' => 91, 'title' => 'E2E pre-proposal evaluation', 'defense_type' => 'Pre-proposal Defense', 'roles' => ['adviser', 'consultant', 'grammarian'], 'is_active' => true]);
    $t->forceFill(['created_at' => '2026-10-01 08:00:00'])->saveQuietly();
    foreach (array_slice(App\\Models\\EvaluationTemplate::STANDARD_QUESTIONS, 0, 2) as $i => $q) { $t->questions()->create(['question' => $q, 'sort_order' => $i + 1]); }
    App\\Models\\DefenseSchedule::whereKey(44)->update(['outcome' => 'passed']);
    echo '@@1';`);
  await student(page);
  await page.goto('/student/experts?tab=evaluations');
  await tidy(page);
  const form = page.locator('.xh-ev-form').first();
  await expect(form, 'the sheet is waiting').toBeVisible();
  // Every expert on the sheet: open it, pick "Very good" on each row.
  for (const card of await form.locator('.xh-ex').all()) {
    if (!(await card.getAttribute('open') !== null)) { await card.locator('summary').click(); }
    for (const q of await card.locator('.xh-ev-q').all()) { await q.locator('td').nth(3).locator('label').click(); }
  }
  await shot(page, testInfo, '4a-student-filled-sheet');
  await form.locator('button[type="submit"]').click();
  // Sending asks once more, with each expert's average.
  const confirm = page.locator('dialog[open] button', { hasText: /send/i }).last();
  if (await confirm.isVisible().catch(() => false)) { await confirm.click(); }
  await expect.poll(() => php(`echo '@@'.App\\Models\\ExpertEvaluation::where('id', '>', ${before.ExpertEvaluation})->count();`)).toBeGreaterThan(0);
  await expect(page.locator('.xh-ev-history')).toContainText('Marites Castillo');
  await shot(page, testInfo, '4b-student-sent');

  const capsa = await staff(browser, 'capsa/evaluations', '/capsa/evaluations/experts/121');
  await expect(capsa.locator('main')).toContainText('Marites Castillo');
  // Who rated is not shown - the count of reviews is, ours included.
  const n = php(`echo '@@'.App\\Models\\ExpertEvaluation::where('expert_id', 121)->count();`);
  await expect(capsa.locator('main')).toContainText(`${n} ${n === 1 ? 'review' : 'reviews'} from`);
  await shot(capsa, testInfo, '4c-capsa-sees-evaluation');
  await capsa.context().close();
});
