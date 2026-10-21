<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Profile photos on the Team page come straight from Google.
 *
 * Google can refuse a photo that is requested with a referrer, and a refused
 * photo used to leave a broken-image icon where the face should be. Every
 * picture on the page is asked for without a referrer, and falls back to the
 * person's initials (or nothing) when it still fails - as the Experts page
 * already does.
 */
class TeamAvatarImagesTest extends TestCase
{
    /** @return list<string> */
    private function imageTags(): array
    {
        // Blade echoes such as {{ $member->avatarUrl() }} contain a ">", so they are skipped over whole.
        // The page and the partials it includes for each screen.
        $views = [resource_path('views/student/team.blade.php'), ...glob(resource_path('views/student/team/*.blade.php'))];
        preg_match_all('/<img\b(?:\{\{.*?\}\}|[^>])*>/s', implode("\n", array_map('file_get_contents', $views)), $matches);

        return $matches[0];
    }

    public function test_the_team_page_has_profile_photos_to_check(): void
    {
        $this->assertGreaterThanOrEqual(12, count($this->imageTags()));
    }

    public function test_every_photo_is_requested_without_a_referrer(): void
    {
        foreach ($this->imageTags() as $tag) {
            $this->assertStringContainsString('referrerpolicy="no-referrer"', $tag, $tag);
        }
    }

    public function test_every_photo_has_a_fallback_when_it_fails_to_load(): void
    {
        foreach ($this->imageTags() as $tag) {
            $this->assertStringContainsString('onerror="', $tag, $tag);
        }
    }

    /** The server-drawn photos fall back to the initials they carry. */
    public function test_server_drawn_photos_carry_initials_to_fall_back_to(): void
    {
        $serverDrawn = array_filter($this->imageTags(), fn (string $tag): bool => str_contains($tag, 'src="{{'));

        $this->assertCount(9, $serverDrawn);

        foreach ($serverDrawn as $tag) {
            $this->assertStringContainsString('data-initials="{{', $tag, $tag);
        }
    }
}
