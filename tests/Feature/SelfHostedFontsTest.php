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
}
