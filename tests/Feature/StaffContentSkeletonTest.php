<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The staff portals - Admin, CaPSA/TSA and the experts' - load the way the
 * student portal does: the first time a page is opened since signing in, its
 * content comes up as its own skeleton (partials/content-skeleton), and every
 * page holds its first paint until it has been read in full
 * (partials/render-whole), so the skeleton is there on the first frame.
 */
class StaffContentSkeletonTest extends TestCase
{
    use RefreshDatabase;

    private function adviser(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $roleId = DB::table('roles')->insertGetId(['role_name' => 'adviser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('expert_roles')->insert(['expert_id' => $expert->expert_id, 'role_id' => $roleId]);

        return $expert;
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function staffPages(): array
    {
        return [
            'admin' => ['admin', 'admin.settings'],
            'capsa' => ['capsa', 'capsa.sections'],
            'expert' => ['adviser', 'instructor.teams'],
        ];
    }

    private function page(string $who, string $route): string
    {
        $expert = match ($who) {
            'admin' => CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]),
            'capsa' => $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true])),
            default => $this->adviser(),
        };

        return $this->actingAs($expert, 'expert')->get(route($route))->assertOk()->getContent();
    }

    #[DataProvider('staffPages')]
    public function test_a_staff_page_comes_up_as_its_skeleton_once_per_sign_in(string $who, string $route): void
    {
        $html = $this->page($who, $route);

        $this->assertSame(1, substr_count($html, 'window.__contentSkeleton = true;'));
        $this->assertStringContainsString("var key = 'csk:".substr(sha1(session()->getId()), 0, 12).":' + ", $html);
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*\(function \(\) \{\s*if \(window\.__contentSkeleton\)/', $html);
    }

    #[DataProvider('staffPages')]
    public function test_a_staff_page_holds_its_first_paint_until_read_in_full(string $who, string $route): void
    {
        $this->assertMatchesRegularExpression(
            '/<link rel="expect" href="#student-page-read" blocking="render">\s*<\/head>/',
            $this->page($who, $route),
        );
    }

    /**
     * Staff pages wrap their content in #real-content, which the old
     * skeleton's "shown" rule forces visible by id; the content stays hidden
     * until the skeleton is drawn over it all the same.
     */
    public function test_the_wrapped_staff_content_is_hidden_until_the_skeleton_is_drawn(): void
    {
        $this->assertStringContainsString('html.csk-hide .main > #real-content { opacity: 0 !important; }', $this->page('capsa', 'capsa.sections'));
    }

    /** A new staff page must hold its paint too, or it flashes. */
    public function test_every_page_with_a_staff_sidebar_holds_its_first_paint(): void
    {
        $views = array_merge(
            glob(resource_path('views/{admin,capsa,instructor}/*.blade.php'), GLOB_BRACE),
            glob(resource_path('views/{admin,capsa,instructor}/*/*.blade.php'), GLOB_BRACE),
        );

        $checked = 0;
        foreach ($views as $view) {
            $source = file_get_contents($view);

            if (! preg_match("/@include\\((?:'capsa\\._sidebar'|'instructor\\.partials\\.sidebar'|\\\$portal === 'instructor')/", $source) || ! str_contains($source, '</head>')) {
                continue;
            }

            $checked++;
            $this->assertStringContainsString("@include('partials.render-whole')", $source, basename($view).' does not hold its first paint.');
        }

        $this->assertGreaterThan(20, $checked);
    }

    /** The calendar comes from the server after the page loads; the skeleton waits for it. */
    public function test_the_expert_schedule_holds_the_skeleton_for_its_calendar(): void
    {
        $source = file_get_contents(resource_path('views/instructor/schedule.blade.php'));

        $this->assertStringContainsString('const calendarDrawn = window.contentSkeleton ? window.contentSkeleton.hold() : null;', $source);
        $this->assertStringContainsString('initCalendar().finally(() => { if (calendarDrawn) calendarDrawn(); });', $source);
    }
}
