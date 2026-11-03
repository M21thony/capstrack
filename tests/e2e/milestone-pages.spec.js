import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Manage milestones, CaPSA to student: she sets up her program, adds a
 * milestone, opens it to teams, gives it a requirement, and deletes another;
 * the team sees the opened one on its tracker.
 *
 *   PW_BASE_URL=http://127.0.0.1:8002 npx playwright test milestone-pages
 *
 * It seeds its own CaPSA (BSIT-4X) and a one-member team, and takes them out after.
 */
test.skip(!process.env.PW_BASE_URL, 'point PW_BASE_URL at the test server');
test.use({ viewport: { width: 1440, height: 900 } });
test.describe.configure({ mode: 'serial' });

const php = (code) => {
  const out = execFileSync('php', ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' });
  const at = out.lastIndexOf('@@');
  return at < 0 ? null : JSON.parse(out.slice(at + 2).trim());
};

const CAPSA = 'pw-ms-capsa@cspc.edu.ph';
const LEADER = 'pw-ms-ana@my.cspc.edu.ph';
const TEAM = 'PW Milestones';
const NEW = 'PW Prototype demo';
const GONE = 'PW Throwaway';
const UPLOAD = 'PW Demo video';

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

test.beforeAll(() => php(`${CLEAN}
  $capsa = App\\Models\\CapstoneExpert::create(['email' => '${CAPSA}', 'first_name' => 'Lea', 'last_name' => 'Santos',
    'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4X']]);
  $capsa->forceFill(['tour_completed_at' => now()])->save();
  $role = Illuminate\\Support\\Facades\\DB::table('roles')->where('role_name', App\\Models\\CapstoneExpert::CAPSA_ROLE)->value('role_id');
  Illuminate\\Support\\Facades\\DB::table('expert_roles')->insertOrIgnore(['expert_id' => $capsa->expert_id, 'role_id' => $role]);
  $s = App\\Models\\Student::create(['email' => '${LEADER}', 'f_name' => 'Ana', 'l_name' => 'Archer', 'course' => 'BSIT', 'year_section' => '4th · X']);
  $s->forceFill(['tutorial_completed_at' => now()])->save();
  $team = App\\Models\\Team::create(['team_name' => '${TEAM}', 'leader_id' => $s->student_id, 'team_status' => 'Active', 'is_confirmed' => true]);
  App\\Models\\StudentSchoolYear::create(['student_id' => $s->student_id, 'team_id' => $team->team_id]);
  echo '@@1';`));

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

const LIST = '/capsa/team-management?tab=milestone_list';

test('setup: the preview follows the choice, and the department milestones are made', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(LIST);
  await page.getByRole('link', { name: 'Set up BSIT milestones' }).click();
  await expect(page).toHaveURL(/milestones\/setup\/BSIT$/i);

  const count = page.locator('#su-n');
  const department = await count.textContent();
  expect(Number(department)).toBeGreaterThan(0);
  await shot(page, testInfo, 'setup');

  await page.getByText('Start empty', { exact: true }).click();
  await expect(count).toHaveText('0');
  await expect(page.locator('[data-list=department]')).toBeHidden();
  await page.getByText("Use the department's milestones", { exact: true }).click();
  await expect(count).toHaveText(department);

  await page.getByRole('button', { name: 'Set up BSIT milestones' }).click();
  await expect(page).toHaveURL(/tab=milestone_list/);
  await expect(page.locator('.mlx-name').first()).toBeVisible();
  await shot(page, testInfo, 'list-after-setup');
});

test('a new milestone is refused without a name, then made, opened and given a requirement', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(LIST);
  await page.getByRole('link', { name: 'New milestone' }).first().click();

  await page.getByRole('button', { name: 'Create milestone' }).click();
  await expect(page.locator('#ml-err')).toHaveText('Give the milestone a name.');

  await page.fill('[name=name]', NEW);
  await page.fill('[name=description]', 'Show the working system to the panel.');
  await page.fill('[name=due_on]', '2026-12-15');
  await shot(page, testInfo, 'new-milestone');
  await page.getByRole('button', { name: 'Create milestone' }).click();

  await expect(page.locator('h1')).toHaveText(NEW);
  await page.getByRole('button', { name: 'Open to teams' }).click();
  await expect(page.getByRole('button', { name: 'Mark finished' })).toBeVisible();

  await page.getByRole('link', { name: 'Add requirement' }).click();
  await page.fill('[name=title]', UPLOAD);
  await page.getByRole('button', { name: 'Add requirement' }).click();
  await expect(page.locator('.ms-req', { hasText: UPLOAD })).toContainText('Upload file');
  await shot(page, testInfo, 'milestone-page');
});

test('the delete sheet opens, cancels, and deletes', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-expert/capsa?email=${CAPSA}`);
  await page.goto(LIST);
  await page.getByRole('link', { name: 'New milestone' }).first().click();
  await page.fill('[name=name]', GONE);
  await page.getByRole('button', { name: 'Create milestone' }).click();
  await expect(page.locator('h1')).toHaveText(GONE);

  await page.getByRole('link', { name: 'Edit milestone' }).click();
  await page.getByRole('button', { name: 'Delete milestone' }).click();
  const sheet = page.locator('dialog[open]');
  await expect(sheet).toBeVisible();
  await expect(sheet).toContainText(GONE);
  await shot(page, testInfo, 'delete-sheet');
  await sheet.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.locator('dialog[open]')).toHaveCount(0);

  await page.getByRole('button', { name: 'Delete milestone' }).click();
  await page.locator('dialog[open]').getByRole('button', { name: 'Delete milestone' }).click();
  await expect(page).toHaveURL(/tab=milestone_list/);
  await expect(page.locator('.mlx-name', { hasText: GONE })).toHaveCount(0);
  await expect(page.locator('.mlx-name', { hasText: NEW })).toBeVisible();
});

test('the team sees the opened milestone and its requirement', async ({ page }, testInfo) => {
  await as(page, `/__dev-login-student/progress?email=${LEADER}`);
  const line = page.locator('.pt-item', { hasText: UPLOAD });
  await line.evaluate((el) => el.closest('details')?.setAttribute('open', ''));
  await expect(line).toBeVisible();
  await shot(page, testInfo, 'student-tracker');
});
