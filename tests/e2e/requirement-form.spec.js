import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * The Requirement form, CaPSA to student: she adds an Upload file and a
 * Defense, edits one, removes one, and the team sees the instructions on
 * its tracker and in the upload drawer.
 *
 *   PW_BASE_URL=http://127.0.0.1:8002 npx playwright test requirement-form
 *
 * It seeds its own CaPSA (BSIT-4Y), her checklist and a one-member team,
 * and takes them all out after.
 */
test.skip(!process.env.PW_BASE_URL, 'point PW_BASE_URL at the test server');
test.use({ viewport: { width: 1440, height: 900 } });
test.describe.configure({ mode: 'serial' });

const php = (code) => {
  const out = execFileSync('php', ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' });
  const at = out.lastIndexOf('@@');
  return at < 0 ? null : JSON.parse(out.slice(at + 2).trim());
};

const CAPSA = 'pw-req-capsa@cspc.edu.ph';
const LEADER = 'pw-req-ana@my.cspc.edu.ph';
const TEAM = 'PW Requirements';
const UPLOAD = 'PW Endorsement letter';
const DEFENSE = 'PW Mock defense';
const STEPS = 'Signed by the adviser and the dean.\nPDF only.';

const CLEAN = `
  $ids = App\\Models\\Student::where('email', '${LEADER}')->pluck('student_id');
  $team = App\\Models\\Team::where('team_name', '${TEAM}')->value('team_id');
  $capsa = App\\Models\\CapstoneExpert::withTrashed()->where('email', '${CAPSA}')->value('expert_id');
  if ($team) { App\\Models\\DeliverableCheck::where('team_id', $team)->delete(); }
  App\\Models\\StudentSchoolYear::whereIn('student_id', $ids)->delete();
  App\\Models\\Team::where('team_name', '${TEAM}')->delete();
  App\\Models\\Student::whereIn('student_id', $ids)->delete();
  if ($capsa) {
    foreach (App\\Models\\Checklist::withTrashed()->where('capsa_id', $capsa)->get() as $c) {
      App\\Models\\Deliverable::withTrashed()->whereIn('checklist_milestone_id', $c->milestones()->withTrashed()->pluck('id'))->forceDelete();
      $c->milestones()->withTrashed()->forceDelete();
      $c->forceDelete();
    }
    Illuminate\\Support\\Facades\\DB::table('expert_roles')->where('expert_id', $capsa)->delete();
    App\\Models\\CapstoneExpert::withTrashed()->where('expert_id', $capsa)->forceDelete();
  }`;

let milestone;

test.beforeAll(() => {
  milestone = php(`${CLEAN}
    $capsa = App\\Models\\CapstoneExpert::create(['email' => '${CAPSA}', 'first_name' => 'Pat', 'last_name' => 'Reyes',
      'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4Y']]);
    $capsa->forceFill(['tour_completed_at' => now()])->save();
    $role = Illuminate\\Support\\Facades\\DB::table('roles')->where('role_name', App\\Models\\CapstoneExpert::CAPSA_ROLE)->value('role_id');
    Illuminate\\Support\\Facades\\DB::table('expert_roles')->insertOrIgnore(['expert_id' => $capsa->expert_id, 'role_id' => $role]);
    $checklist = App\\Support\\MilestoneTemplate::createChecklist($capsa->expert_id, 'BSIT');
    $checklist->milestones()->update(['opened_at' => now()]);
    $s = App\\Models\\Student::create(['email' => '${LEADER}', 'f_name' => 'Ana', 'l_name' => 'Archer', 'course' => 'BSIT', 'year_section' => '4th · Y']);
    $s->forceFill(['tutorial_completed_at' => now()])->save();
    $team = App\\Models\\Team::create(['team_name' => '${TEAM}', 'leader_id' => $s->student_id, 'team_status' => 'Active', 'is_confirmed' => true]);
    App\\Models\\StudentSchoolYear::create(['student_id' => $s->student_id, 'team_id' => $team->team_id]);
    echo '@@' . json_encode($checklist->milestones()->value('id'));`);
});

test.afterAll(() => php(`${CLEAN} echo '@@1';`));

// Every page under test must load without a script error.
test.beforeEach(({ page }) => {
  page.on('pageerror', (e) => { throw e; });
});

async function as(page, url) {
  await page.context().clearCookies();
  await page.goto(url);
  await page.evaluate(() => document.getElementById('erp-overlay')?.remove());
}

// Waits out the page skeleton and any fade so the picture shows the real page.
const shot = async (page, testInfo, name) => {
  await page.locator('#csk').waitFor({ state: 'detached' });
  const body = await page.screenshot({ fullPage: true, animations: 'disabled' });
  await testInfo.attach(name, { body, contentType: 'image/png' });
};

const row = (page, name) => page.locator('.ms-req', { hasText: name });

test('Which defense shows only for a Defense, and a blank name is refused', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(`/capsa/team-management/milestones/${milestone}`);
  await page.getByRole('link', { name: 'Add requirement' }).click();

  const stage = page.locator('#ml-stage');
  await expect(page.locator('input[value=upload]')).toBeChecked();
  await expect(stage).toBeHidden();
  await page.getByText('Defense', { exact: true }).click();
  await expect(stage).toBeVisible();
  await shot(page, testInfo, 'defense-picked');
  await page.getByText('Upload file', { exact: true }).click();
  await expect(stage).toBeHidden();

  await page.getByRole('button', { name: 'Add requirement' }).click();
  await expect(page.locator('#ml-err')).toHaveText('Give the requirement a name.');
});

test('the CaPSA adds an Upload file with instructions', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(`/capsa/team-management/milestones/${milestone}/requirements/new`);
  await page.fill('[name=title]', UPLOAD);
  await page.fill('[name=instructions]', STEPS);
  await shot(page, testInfo, 'add-upload');
  await page.getByRole('button', { name: 'Add requirement' }).click();

  await expect(page).toHaveURL(new RegExp(`/milestones/${milestone}$`));
  await expect(row(page, UPLOAD)).toContainText('Upload file');
  await expect(row(page, UPLOAD)).toContainText('0 of 1 done');
  expect(php(`echo '@@' . json_encode(App\\Models\\Deliverable::where('title', '${UPLOAD}')->first(['kind', 'instructions']));`))
    .toEqual({ kind: 'form', instructions: STEPS });
});

test('the CaPSA adds a Defense and it reads as that defense', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(`/capsa/team-management/milestones/${milestone}/requirements/new`);
  await page.fill('[name=title]', DEFENSE);
  await page.getByText('Defense', { exact: true }).click();
  await page.selectOption('[name=defense_type]', 'Pre Oral Defense');
  await page.getByRole('button', { name: 'Add requirement' }).click();

  await expect(row(page, DEFENSE)).toContainText('Pre Oral Defense');
  await expect(row(page, DEFENSE)).toContainText('0 of 1 done');
  await shot(page, testInfo, 'milestone-rows');

  await row(page, DEFENSE).getByRole('link', { name: 'Edit' }).click();
  await expect(page.locator('input[value=defense]')).toBeChecked();
  await expect(page.locator('[name=defense_type]')).toHaveValue('Pre Oral Defense');
});

test('editing keeps the upload an upload', async ({ page }) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(`/capsa/team-management/milestones/${milestone}`);
  await row(page, UPLOAD).getByRole('link', { name: 'Edit' }).click();
  await expect(page.locator('[name=instructions]')).toHaveValue(STEPS);
  await page.fill('[name=due_date]', '2026-12-01');
  await page.getByRole('button', { name: 'Save requirement' }).click();

  await expect(row(page, UPLOAD)).toBeVisible();
  expect(php(`echo '@@' . json_encode(App\\Models\\Deliverable::where('title', '${UPLOAD}')->first(['kind', 'instructions']));`))
    .toEqual({ kind: 'form', instructions: STEPS });
});

test('the remove sheet opens, cancels, and removes', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(`/capsa/team-management/milestones/${milestone}`);
  await row(page, DEFENSE).getByRole('link', { name: 'Edit' }).click();

  await page.getByRole('button', { name: 'Remove requirement' }).click();
  const sheet = page.locator('dialog[open]');
  await expect(sheet).toBeVisible();
  await expect(sheet).toContainText(DEFENSE);
  await shot(page, testInfo, 'remove-sheet');
  await sheet.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.locator('dialog[open]')).toHaveCount(0);

  await page.getByRole('button', { name: 'Remove requirement' }).click();
  await page.locator('dialog[open]').getByRole('button', { name: 'Remove requirement' }).click();
  await expect(page).toHaveURL(new RegExp(`/milestones/${milestone}$`));
  await expect(row(page, DEFENSE)).toHaveCount(0);
});

test('the team reads the instructions on its tracker and in the drawer', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-student/progress?email=${LEADER}`);

  const line = page.locator('.pt-item', { hasText: UPLOAD });
  await line.evaluate((el) => el.closest('details')?.setAttribute('open', ''));
  await expect(line.locator('.pt-item-instr')).toHaveText(STEPS);
  await shot(page, testInfo, 'student-tracker');

  await line.getByRole('button', { name: 'Upload' }).click();
  const instr = page.locator('dialog[open] .up-instr');
  await expect(instr).toBeVisible();
  await expect(instr.locator('p')).toHaveText(STEPS);
  await shot(page, testInfo, 'student-drawer');

  await page.keyboard.press('Escape');
  await expect(page.locator('dialog[open]')).toHaveCount(0);
});
