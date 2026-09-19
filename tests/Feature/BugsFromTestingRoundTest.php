<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Four things found by using the system rather than by reading it.
 *
 * They have nothing in common except that: a dead Decline button, a document
 * a team could not open, a form asking for a name nobody knew yet, and a
 * dialog describing an action it does not perform. Each one is here because
 * every existing test passed while it was broken.
 */
class BugsFromTestingRoundTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $email = 'jerome@my.cspc.edu.ph'): Student
    {
        return Student::create([
            'email' => $email,
            'f_name' => 'Jerome',
            'l_name' => 'Abella',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);
    }

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);
    }

    // ---------------------------------------------------------------
    // Student / Team Management - the Decline button did nothing
    // ---------------------------------------------------------------

    /**
     * setAvatar() was declared inside the @if that only runs for a student
     * who has a team. openDeclineModal() calls it on its third line, so on
     * the page of a student with no team - which is every student who can
     * receive an invitation - the click threw "setAvatar is not defined"
     * before reaching the line that opens the dialog. Nothing appeared and
     * nothing was logged anywhere a person would look. Accept worked the
     * whole time, because it goes through showConfirm(), which was already
     * outside that conditional.
     */
    public function test_a_student_with_no_team_gets_the_whole_decline_path(): void
    {
        $html = $this->actingAs($this->student(), 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('function openDeclineModal(', $html);
        $this->assertStringContainsString('function submitDecline(', $html);
        $this->assertStringContainsString('function setAvatar(', $html);
    }

    /** And a student who does have one is not served it twice. */
    public function test_a_student_in_a_team_gets_setavatar_exactly_once(): void
    {
        $student = $this->student('leader@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Codebreakers', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $html = $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'function setAvatar('));
    }

    /**
     * The same mistake as setAvatar(), on a wider blast radius. CREATION_ALLOWED,
     * JOINING_ALLOWED and their two message constants were declared inside the
     * leader-only conditional that gates most of this page's script, but
     * updateModalUI() - called by every visitor's DOMContentLoaded handler,
     * leader or not, team or none - reads them on load to decide whether the
     * recruitment modal opens or explains itself as closed. For anyone but the
     * leader that threw "CREATION_ALLOWED is not defined", which aborted the
     * handler before it reached the skeleton-loader fade-out further down - so
     * the page never got past its loading skeleton for a plain member, or for
     * a student with no team at all.
     */
    public function test_a_student_with_no_team_gets_the_recruitment_rule_constants(): void
    {
        $html = $this->actingAs($this->student(), 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('const CREATION_ALLOWED', $html);
        $this->assertStringContainsString('const JOINING_ALLOWED', $html);
        $this->assertStringContainsString('const CREATION_CLOSED_MSG', $html);
        $this->assertStringContainsString('const JOINING_CLOSED_MSG', $html);
    }

    /** A plain member - in a team, but not leading it - needs them too. */
    public function test_a_non_leader_member_gets_the_recruitment_rule_constants(): void
    {
        $leader = $this->student('leader@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Codebreakers', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $member = $this->student('member@my.cspc.edu.ph');
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $html = $this->actingAs($member, 'web')->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString('const CREATION_ALLOWED', $html);
        $this->assertStringContainsString('const JOINING_ALLOWED', $html);
    }

    /** And the leader, who always had them, is not served them twice. */
    public function test_a_team_leader_gets_the_recruitment_rule_constants_exactly_once(): void
    {
        $student = $this->student('leader@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Codebreakers', 'leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $html = $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'const CREATION_ALLOWED'));
        $this->assertSame(1, substr_count($html, 'const JOINING_ALLOWED'));
    }

    /**
     * The leader is shown as the leader on their own team.
     *
     * The roster decided this by asking whether a member's role was the
     * string "Leader". No row has ever held that: the leader's role is
     * spelled out as "Project Head/Network Engineer", and most rows have no
     * role recorded at all. So the test was false for everybody, and the
     * person who created the team read "Team Member / MEMBER" beside their
     * own name. It reads teams.leader_id now, which is what the rest of the
     * page - and every permission check on it - has always used.
     */
    public function test_the_leader_is_not_labelled_a_member(): void
    {
        $leader = $this->student('head@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create([
            'student_id' => $leader->student_id, 'team_id' => $team->team_id,
        ]);

        $mate = Student::create([
            'email' => 'mate@my.cspc.edu.ph', 'f_name' => 'John', 'l_name' => 'Aurillas',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);
        StudentSchoolYear::create([
            'student_id' => $mate->student_id, 'team_id' => $team->team_id,
        ]);

        $html = $this->actingAs($leader, 'web')
            ->get(route('student.team'))
            ->assertOk()
            ->getContent();

        // One LEADER badge and one MEMBER badge, not two MEMBERs.
        $this->assertSame(1, substr_count($html, '>LEADER</div>'));
        $this->assertSame(1, substr_count($html, '>MEMBER</div>'));
    }

    /** A role that is recorded is shown instead of the generic wording. */
    public function test_a_recorded_role_is_shown(): void
    {
        $role = StudentRole::firstOrCreate(['s_role_name' => StudentRole::LEADER_ROLE_NAME]);

        $leader = $this->student('head@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Bulbasaurus', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
            's_role_id' => $role->s_role_id,
        ]);

        $html = $this->actingAs($leader, 'web')->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString(StudentRole::LEADER_ROLE_NAME, $html);
        $this->assertSame(1, substr_count($html, '>LEADER</div>'));
    }

    // ---------------------------------------------------------------
    // Student / Experts - the original PDF
    // ---------------------------------------------------------------

    /** @return array{0: Student, 1: Team, 2: CapstonePreProposal} */
    private function teamWithApprovedProposal(bool $withFile = true): array
    {
        Storage::fake();

        $student = $this->student('applicant@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Codebreakers', 'leader_id' => $student->student_id]);
        $enrolment = StudentSchoolYear::create([
            'student_id' => $student->student_id, 'team_id' => $team->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $enrolment->s_y_id,
            'team_id' => $team->team_id,
            'document_path' => 'pre-proposals/approved.pdf',
            'concepts' => ['title' => 'A model that grades essays'],
            'top_score' => 20,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        if ($withFile) {
            Storage::put('pre-proposals/approved.pdf', '%PDF-1.4 pretend');
        }

        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'A model that grades essays',
            'sdg_goals' => 'SDG 4 - Quality Education',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'Marking is slow and inconsistent.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology',
            'duplication_status' => 'free',
            'approved_status' => true,
            'submitted_at' => now(),
        ]);

        $team->update(['selected_pre_proposal_id' => $proposal->id]);

        return [$student, $team, $proposal];
    }

    /**
     * The apply form tells a team their approved pre-proposal goes out with
     * the application. The preview beside that sentence showed the fields
     * they typed and never the document itself, which is the thing actually
     * being sent.
     */
    public function test_the_preview_offers_the_original_pdf(): void
    {
        [$student, , $proposal] = $this->teamWithApprovedProposal();

        $html = $this->actingAs($student, 'web')
            ->get(route('student.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('student.pre-proposal.document', $proposal), $html);
        // A button opening a panel of its own, the way the expert side shows
        // a student's proposal - not an iframe wedged above seven text
        // fields, which is neither a readable document nor a readable
        // summary.
        $this->assertStringContainsString('View Original PDF', $html);
        $this->assertStringContainsString('id="previewDialog"', $html);
    }

    /** A button leading to a 404 is worse than no button. */
    public function test_no_pdf_link_when_the_upload_is_gone(): void
    {
        [$student] = $this->teamWithApprovedProposal(withFile: false);

        $html = $this->actingAs($student, 'web')->get(route('student.experts'))->assertOk()->getContent();

        $this->assertStringNotContainsString('View Original PDF', $html);
        $this->assertStringNotContainsString('id="previewDialog"', $html);
    }

    public function test_a_team_can_open_its_own_document(): void
    {
        [$student, , $proposal] = $this->teamWithApprovedProposal();

        $this->actingAs($student, 'web')
            ->get(route('student.pre-proposal.document', $proposal))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=Pre-Proposal-1.pdf');
    }

    /** Somebody else's title, on the other hand, is none of their business. */
    public function test_another_teams_document_is_refused(): void
    {
        [, , $proposal] = $this->teamWithApprovedProposal();

        $outsider = $this->student('outsider@my.cspc.edu.ph');
        $theirTeam = Team::create(['team_name' => 'Others', 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => $theirTeam->team_id]);

        $this->actingAs($outsider, 'web')
            ->get(route('student.pre-proposal.document', $proposal))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Admin / Experts - Add CaPSA
    // ---------------------------------------------------------------

    /** The message has to name somebody, and their address is who they are. */
    public function test_the_confirmation_falls_back_to_the_address(): void
    {
        Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE]);

        $this->actingAs($this->admin(), 'expert')
            ->postJson(route('admin.experts.capsa.store'), ['email' => 'r.villanueva@cspc.edu.ph'])
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'r.villanueva@cspc.edu.ph was made a CaPSA. '
                    .'They can sign in with r.villanueva@cspc.edu.ph.',
            ]);
    }

    /** Leaving sections out must not clear the ones somebody already has. */
    public function test_omitting_sections_leaves_existing_ones_alone(): void
    {
        Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE]);

        $existing = CapstoneExpert::factory()->create([
            'email' => 'kept@cspc.edu.ph', 'approved_status' => true,
            'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);

        $this->actingAs($this->admin(), 'expert')
            ->postJson(route('admin.experts.capsa.store'), ['email' => 'kept@cspc.edu.ph'])
            ->assertOk();

        $this->assertSame(['BSIT-4A'], $existing->fresh()->sections);
    }

    /** The row has to say whose account it is, and why it has no name yet. */
    public function test_a_nameless_row_shows_the_address_and_says_why(): void
    {
        $capsaRole = Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE]);

        $nameless = CapstoneExpert::factory()->create([
            'email' => 'r.villanueva@cspc.edu.ph', 'first_name' => '', 'last_name' => '',
            'middle_name' => null, 'suffix' => null, 'credentials' => null,
            'approved_status' => true, 'is_admin' => false, 'sections' => [],
        ]);
        $nameless->roles()->attach($capsaRole->role_id);

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Name fills in when they first sign in', $html);
        // Every dialog opened off this row has something to call them.
        $this->assertStringContainsString('data-name="r.villanueva@cspc.edu.ph"', $html);
    }

    // ---------------------------------------------------------------
    // Admin / Experts - the deactivate dialog
    // ---------------------------------------------------------------

    /**
     * destroy() calls delete() on a SoftDeletes model and reactivate() sits
     * directly beneath it, so "permanently remove" and "cannot be undone"
     * were describing something the button has never done.
     */
    public function test_the_deactivate_dialog_describes_deactivating(): void
    {
        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.experts'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Deactivate Account', $html);
        $this->assertStringNotContainsString('permanently remove', $html);
        $this->assertStringNotContainsString('cannot be undone', $html);

        // The typed word and the two gates that read it have to agree, or
        // the button never enables at all.
        $this->assertStringContainsString('>deactivate</code> to confirm', $html);
        $this->assertSame(2, substr_count($html, "!== 'deactivate'"));
        $this->assertStringNotContainsString("!== 'remove'", $html);
    }
}
