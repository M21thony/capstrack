<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\GoogleDriveToken;
use App\Models\GradingRubric;
use App\Models\Role;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * The expert and CaPSA pages open as they will stay: nothing arrives after
 * the page is on screen to push what is already there out of the way, and
 * nothing is drawn plain first and dressed a moment later.
 */
class StaffPagesOpenFinishedTest extends TestCase
{
    use RefreshDatabase;

    private string $hotFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Script tags as the dev server writes them, so the test does not
        // need a build in public/build.
        $this->hotFile = storage_path('framework/testing/vite.hot');
        File::ensureDirectoryExists(dirname($this->hotFile));
        File::put($this->hotFile, 'http://vite.test');
        Vite::useHotFile($this->hotFile);
    }

    protected function tearDown(): void
    {
        File::delete($this->hotFile);

        parent::tearDown();
    }

    private function capsa(): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true,
            'sections' => ['BSIT-4B'],
        ]));
    }

    private function adviser(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    public function test_a_page_its_script_draws_is_held_until_the_script_has_run(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.financial'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]*resources/js/financial/capsa\.js" blocking="render">#', $html);
    }

    public function test_other_scripts_do_not_hold_the_page(): void
    {
        $this->assertSame([
            'resources/js/financial/capsa.js',
            'resources/js/financial/student.js',
            'resources/js/defenses/defenses.js',
        ], AppServiceProvider::PAGE_DRAWING_SCRIPTS);

        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.team_management'))->assertOk()->getContent();

        $this->assertStringNotContainsString('blocking="render"', $html);
    }

    public function test_the_fee_tabs_keep_their_row_only_when_there_are_fees(): void
    {
        $html = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.financial'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="fx-tabs"[^>]*id="c-fees"\s+hidden\s*>#', $html);
    }

    public function test_the_rubric_list_comes_with_the_page(): void
    {
        GradingRubric::create([
            'rubric_name' => 'Title Defense Evaluation',
            'rubric_type' => 'Title Defense',
            'description' => 'How the panel scores a title defense.',
            'total_points' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->capsa(), 'expert')->get(route('capsa.rubric_management'))->assertOk();

        $response->assertViewHas('initialRubrics', fn (array $rubrics): bool => count($rubrics) === 1
            && $rubrics[0]['rubric_name'] === 'Title Defense Evaluation');
        $html = $response->getContent();
        $this->assertStringContainsString('rubrics = ([{', $html);
        $this->assertStringContainsString('Title Defense Evaluation', $html);
        // Drawn straight from it; the list is not fetched as the page opens.
        $this->assertStringNotContainsString("\nloadRubrics();\n</script>", $html);
    }

    public function test_resources_says_where_drive_stands_as_it_opens(): void
    {
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')->get(route('capsa.resources'))->assertOk()
            ->assertDontSee('Checking Google Drive')
            ->assertSee('Drive not connected')
            ->assertSee('id="gdrive-connect-btn" style="font-size:12px;display:inline-flex"', false);

        GoogleDriveToken::create(['user_id' => $capsa->expert_id, 'access_token' => 'token']);

        $this->actingAs($capsa, 'expert')->get(route('capsa.resources'))->assertOk()
            ->assertSee('Drive connected')
            ->assertSee('id="gdrive-connect-btn" style="font-size:12px;display:none"', false);
    }

    public function test_the_notification_settings_are_styled_and_filled_before_they_are_shown(): void
    {
        $html = $this->actingAs($this->adviser(), 'expert')->get(route('instructor.profile'))->assertOk()->getContent();

        $styles = strpos($html, '.notif-prefs{');
        $card = strpos($html, 'class="notif-prefs');
        $this->assertNotFalse($styles);
        $this->assertNotFalse($card);
        $this->assertLessThan($card, $styles, 'the card\'s styles come ahead of it');

        // The browser's state is written into the box by a script right after
        // it, not once the page has loaded.
        $this->assertMatchesRegularExpression('#<div class="notif-prefs-perm" id="notif-prefs-perm">.*?</div>\s*<script>\s*//[^\n]*\n.*?getElementById\(\'notif-prefs-perm-text\'\)#s', $html);
        $this->assertStringNotContainsString('id="notif-prefs-perm" style="display:none"', $html);
    }

    public function test_the_edit_profile_icon_is_a_whole_path(): void
    {
        $html = $this->actingAs($this->adviser(), 'expert')->get(route('instructor.profile'))->assertOk()->getContent();

        $this->assertStringNotContainsString('a4 0 0 1 0 7.75', $html);
        $this->assertStringContainsString('M16 3.13a4 4 0 0 1 0 7.75', $html);
    }

    public function test_the_teams_tab_is_lit_on_the_pages_under_teams(): void
    {
        $adviser = $this->adviser();

        foreach (['instructor.teams', 'instructor.assignments', 'instructor.progress'] as $route) {
            $html = $this->actingAs($adviser, 'expert')->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#<a href="[^"]*/instructor/teams" class="mtab-item on"\s+aria-current="page"\s*>#', $html, $route);
        }
    }

    public function test_a_phone_opens_team_assignments_at_the_queue(): void
    {
        $html = $this->actingAs($this->adviser(), 'expert')->get(route('instructor.assignments'))->assertOk()->getContent();

        // The first application is shown for the reader, but the page is not
        // scrolled down to it: only a tap on a team does that.
        $this->assertStringContainsString('selectTitle(Number(first.dataset.id), false); else selectAssignment(Number(first.dataset.id), false);', $html);
        $this->assertSame(2, substr_count($html, 'if (reveal) revealPanelOnPhone();'));
    }
}
