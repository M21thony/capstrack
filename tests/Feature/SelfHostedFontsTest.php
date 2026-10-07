<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\PublicAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The portal's fonts come from this site, not Google's: no page waits on
 * another server to draw, a network that blocks Google's does not fall back
 * to system fonts, and no visit is reported to a third party.
 */
class SelfHostedFontsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_loads_fonts_from_google(): void
    {
        $offenders = collect(File::allFiles(resource_path()))
            ->filter(fn ($file) => preg_match('/fonts\.(googleapis|gstatic)\.com/', $file->getContents()))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders);
    }

    public function test_every_font_file_the_stylesheet_names_is_on_the_site(): void
    {
        $css = File::get(public_path('css/fonts.css'));

        preg_match_all('/url\(\.\.\/fonts\/([^)]+\.woff2)\)/', $css, $files);

        $this->assertCount(10, $files[1], 'DM Sans, Poppins 600/700 and IBM Plex Mono 400/500, Latin and Latin Extended each.');

        foreach ($files[1] as $file) {
            $this->assertFileExists(public_path('fonts/'.$file));
            $this->assertStringStartsWith('wOF2', File::get(public_path('fonts/'.$file)), $file);
        }

        foreach (["font-family: 'DM Sans'", "font-family: 'Poppins'", "font-family: 'IBM Plex Mono'"] as $family) {
            $this->assertStringContainsString($family, $css);
        }
    }

    public function test_the_portals_and_sign_in_link_the_sites_own_fonts(): void
    {
        $link = 'href="'.PublicAsset::url('css/fonts.css').'" rel="stylesheet"';

        $this->get(route('login'))->assertOk()->assertSee($link, false);

        $team = Team::create(['team_name' => 'Font Team', 'project_title' => 'Fonts', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->assertSee($link, false);

        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))->assertOk()->assertSee($link, false);
    }

    /**
     * What a page is drawn in before DM Sans is ready is Arial set to DM
     * Sans's measurements - its line height to the pixel - so the text does
     * not grow and push the page down when DM Sans comes in.
     */
    public function test_the_stand_in_for_dm_sans_is_measured_to_it(): void
    {
        $css = File::get(public_path('css/fonts.css'));

        preg_match_all("/@font-face\s*\{[^}]*font-family:\s*'DM Sans Fallback';[^}]*\}/", $css, $faces);

        $this->assertCount(2, $faces[0], 'one face for regular text and one for bold');

        foreach ($faces[0] as $face) {
            $this->assertStringContainsString("local('Arial')", str_replace("local('Arial Bold')", "local('Arial')", $face));
            $this->assertStringContainsString('size-adjust: 102%', $face);
            // DM Sans: 0.992em above the line and 0.310em below, no gap -
            // written before the 102% it is scaled by.
            $this->assertStringContainsString('ascent-override: 97.25%', $face);
            $this->assertStringContainsString('descent-override: 30.39%', $face);
            $this->assertStringContainsString('line-gap-override: 0%', $face);
        }
    }

    /** A font list that names DM Sans names its stand-in straight after it. */
    public function test_every_font_list_falls_back_to_the_measured_stand_in(): void
    {
        $missing = collect([public_path('css'), resource_path()])
            ->flatMap(fn (string $dir) => File::allFiles($dir))
            ->filter(fn ($file) => in_array($file->getExtension(), ['css', 'php'], true))
            ->reject(fn ($file) => $file->getRealPath() === realpath(public_path('css/fonts.css')))
            ->flatMap(function ($file) {
                preg_match_all('/font(?:-family)?\s*:[^;}{\n]*[\'"]DM Sans[\'"](?!\s*,\s*[\'"]DM Sans Fallback)[^;}{\n]*/', $file->getContents(), $lists);

                return array_map(fn (string $list) => $file->getRelativePathname().': '.trim($list), $lists[0]);
            })
            ->values()
            ->all();

        $this->assertSame([], $missing);
    }
}
