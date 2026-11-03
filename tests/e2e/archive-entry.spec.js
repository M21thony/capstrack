import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * The Archive Entry, end to end: a team that passed the Final Oral Defense
 * sends its title, typed abstract and proponents from the archive page, the
 * tracker shows it waiting on the CaPSA, and her Publish closes the line.
 *
 *   PW_BASE_URL=http://127.0.0.1:8002 npx playwright test archive-entry
 *
 * It seeds its own CaPSA (BSIT-4Z, a section nobody else runs), her
 * checklist and a two-member team, and takes them all out after.
 */
test.skip(!process.env.PW_BASE_URL, 'point PW_BASE_URL at the test server');
test.use({ viewport: { width: 1440, height: 900 } });
test.describe.configure({ mode: 'serial' });

// Runs PHP in the app; whatever it echoes after "@@" comes back as JSON.
const php = (code) => {
  const out = execFileSync('php', ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' });
  const at = out.lastIndexOf('@@');
  return at < 0 ? null : JSON.parse(out.slice(at + 2).trim());
};

const CAPSA = 'pw-arc-capsa@cspc.edu.ph';
const LEADER = 'pw-arc-ana@my.cspc.edu.ph';
const MEMBER = 'pw-arc-ben@my.cspc.edu.ph';
const TEAM = 'PW Archivists';
const TITLE = 'PW Tracker for Capstone Teams';
const LINE = 'Archive Entry (Title, Abstract, Proponents)';

const CLEAN = `
  $ids = App\\Models\\Student::whereIn('email', ['${LEADER}', '${MEMBER}'])->pluck('student_id');
  $team = App\\Models\\Team::where('team_name', '${TEAM}')->value('team_id');
  $capsa = App\\Models\\CapstoneExpert::withTrashed()->where('email', '${CAPSA}')->value('expert_id');
  if ($team) {
    App\\Models\\ArchivedDocument::where('team_id', $team)->delete();
    App\\Models\\DefenseSchedule::where('team_id', $team)->delete();
    App\\Models\\DeliverableCheck::where('team_id', $team)->delete();
  }
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

test.beforeAll(() => {
  php(`${CLEAN}
    $capsa = App\\Models\\CapstoneExpert::create(['email' => '${CAPSA}', 'first_name' => 'Pat', 'last_name' => 'Reyes',
      'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4Z']]);
    $capsa->forceFill(['tour_completed_at' => now()])->save();
    $role = Illuminate\\Support\\Facades\\DB::table('roles')->where('role_name', App\\Models\\CapstoneExpert::CAPSA_ROLE)->value('role_id');
    Illuminate\\Support\\Facades\\DB::table('expert_roles')->insertOrIgnore(['expert_id' => $capsa->expert_id, 'role_id' => $role]);
    App\\Support\\MilestoneTemplate::createChecklist($capsa->expert_id, 'BSIT')->milestones()->update(['opened_at' => now()]);
    $members = collect(['${LEADER}' => ['Ana', 'Archer'], '${MEMBER}' => ['Ben', 'Bautista']])->map(fn ($n, $email) => tap(
      App\\Models\\Student::create(['email' => $email, 'f_name' => $n[0], 'l_name' => $n[1], 'course' => 'BSIT', 'year_section' => '4th · Z']),
      fn ($s) => $s->forceFill(['tutorial_completed_at' => now()])->save()));
    $team = App\\Models\\Team::create(['team_name' => '${TEAM}', 'leader_id' => $members['${LEADER}']->student_id,
      'team_status' => 'Active', 'is_confirmed' => true, 'project_title' => '${TITLE}']);
    foreach ($members as $s) { App\\Models\\StudentSchoolYear::create(['student_id' => $s->student_id, 'team_id' => $team->team_id]); }
    App\\Models\\DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Final Oral Defense', 'attempt' => 1,
      'schedule_date' => now(), 'outcome' => App\\Support\\DefenseOutcome::PASSED, 'outcome_recorded_at' => now()]);
    echo '@@1';`);
});

test.afterAll(() => php(`${CLEAN} echo '@@1';`));

async function as(page, url) {
  await page.context().clearCookies();
  await page.goto(url);
  await page.evaluate(() => document.getElementById('erp-overlay')?.remove());
}

const archiveLine = (page) => page.locator('.pt-item', { hasText: LINE });

test('the leader sends title, typed abstract and proponents', async ({ page }) => {
  await as(page, `/__dev-login-student/archive?email=${LEADER}`);
  await page.locator('#open-manuscript').click();

  await expect(page.locator('#ms-title')).toHaveValue(TITLE);
  await expect(page.locator('#ms-proponents')).toHaveAttribute('readonly', '');
  await expect(page.locator('#ms-proponents')).toHaveValue(/Archer, A\..*Bautista, B\./);
  await expect(page.locator('#ms-abstract')).toHaveValue('');
  await expect(page.locator('#manuscript-modal input[type=file]')).toHaveCount(0);

  await page.fill('#ms-abstract', 'We built a tracker for capstone teams. It tracks.');
  await page.locator('#ms-submit').click();
  await expect(page.getByText('Archive Entry sent.')).toBeVisible();
});

test('the tracker shows it waiting on the CaPSA', async ({ page }) => {
  await as(page, `/__dev-login-student/progress?email=${LEADER}`);

  const line = archiveLine(page);
  await expect(line).toContainText('With your CaPSA to publish');
  await expect(line).toContainText('Closes when your CaPSA publishes it in the Archive');
  await expect(line.locator('a.pt-file', { hasText: 'Open it in Archive' })).toHaveAttribute('href', /\/student\/archive$/);
});

test('a member is told the leader sends it', async ({ page }) => {
  await as(page, `/__dev-login-student/archive?email=${MEMBER}`);
  await expect(page.locator('#open-manuscript')).toHaveCount(0);
});

test('the CaPSA publishes it and the line closes', async ({ page }) => {
  await as(page, `/__dev-login-expert/capsa/archive?email=${CAPSA}`);

  const entry = page.locator('[id^="pending-"]', { hasText: TEAM });
  await entry.getByRole('button', { name: 'Publish to Archive' }).click();
  const saved = page.waitForResponse((r) => r.url().includes('/submission') && r.request().method() === 'PATCH');
  await page.getByRole('button', { name: 'Publish', exact: true }).click();
  expect((await saved).status()).toBe(200);

  await as(page, `/__dev-login-student/progress?email=${LEADER}`);
  await expect(archiveLine(page)).toHaveClass(/st-done/);
  await expect(archiveLine(page).locator('a.pt-file')).toHaveCount(0);
});

// Sets the Archive Entry milestone's dates, with the team's entry taken back out.
const setArchiveMilestone = (fields) => php(`$team = App\\Models\\Team::where('team_name', '${TEAM}')->value('team_id');
    $capsa = App\\Models\\CapstoneExpert::where('email', '${CAPSA}')->value('expert_id');
    App\\Models\\ArchivedDocument::where('team_id', $team)->delete();
    App\\Models\\ChecklistMilestone::whereIn('checklist_id', App\\Models\\Checklist::where('capsa_id', $capsa)->pluck('id'))
      ->where('name', 'Archive Entry')->update(${fields});
    echo '@@1';`);

test('a closed Archive Entry takes no new entry', async ({ page }) => {
  setArchiveMilestone(`['closed_at' => now()]`);

  await as(page, `/__dev-login-student/archive?email=${LEADER}`);
  await expect(page.getByText('Your CaPSA has closed the Archive Entry.')).toBeVisible();
  await expect(page.locator('#open-manuscript')).toHaveCount(0);
});

test('an Archive Entry the CaPSA has not opened takes no entry', async ({ page }) => {
  setArchiveMilestone(`['closed_at' => null, 'opened_at' => null]`);

  await as(page, `/__dev-login-student/archive?email=${LEADER}`);
  await expect(page.getByText('Your CaPSA has not opened the Archive Entry yet.')).toBeVisible();
  await expect(page.locator('#open-manuscript')).toHaveCount(0);

  await as(page, `/__dev-login-student/progress?email=${LEADER}`);
  await expect(page.locator('details.pt-group', { has: archiveLine(page) }).locator('summary')).toContainText('Not open yet');
});
