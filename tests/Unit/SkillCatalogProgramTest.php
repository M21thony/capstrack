<?php

namespace Tests\Unit;

use App\Support\SkillCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Each programme has its own areas of expertise.
 *
 * CaPSA asked for this: the four programmes are not the same subject, and one
 * list for all of them offered a BLIS adviser "Embedded Systems" and a BSCS
 * one "Cataloguing & Classification".
 */
class SkillCatalogProgramTest extends TestCase
{
    /** @return string[] */
    private function skillsFor(string $code): array
    {
        return array_merge(...array_values(SkillCatalog::groupsForPrograms([$code])));
    }

    /** Every technical skill belongs to at least one programme. */
    public function test_no_technical_skill_belongs_to_nobody(): void
    {
        $orphans = [];

        foreach (SkillCatalog::groups() as $group => $items) {
            if (in_array($group, SkillCatalog::sharedGroups(), true)) {
                continue;
            }

            foreach ($items as $skill) {
                if (SkillCatalog::programsFor($skill) === []) {
                    $orphans[] = $skill;
                }
            }
        }

        $this->assertSame([], $orphans, 'These skills are offered to no programme at all.');
    }

    /**
     * The research and language groups are every programme's.
     *
     * A capstone in any of the four needs a literature review and a
     * proofread; those are about writing one, not about a discipline.
     */
    public function test_research_and_language_are_offered_to_every_programme(): void
    {
        foreach (SkillCatalog::programCodes() as $code) {
            $offered = SkillCatalog::groupsForPrograms([$code]);

            foreach (SkillCatalog::sharedGroups() as $shared) {
                $this->assertArrayHasKey($shared, $offered, "{$code} was not offered {$shared}.");
                $this->assertSame(SkillCatalog::itemsIn($shared), $offered[$shared]);
            }
        }
    }

    /** What each programme is about, spot-checked where it should differ. */
    public function test_each_programme_gets_the_skills_it_teaches(): void
    {
        $this->assertContains('Machine Learning', $this->skillsFor('BSCS'));
        $this->assertContains('Artificial Intelligence', $this->skillsFor('BSCS'));

        $this->assertContains('Network Systems', $this->skillsFor('BSIT'));
        $this->assertContains('Internet of Things (IoT)', $this->skillsFor('BSIT'));

        $this->assertContains('Records Management', $this->skillsFor('BSIS'));
        $this->assertContains('Information Management', $this->skillsFor('BSIS'));

        $this->assertContains('Cataloguing & Classification', $this->skillsFor('BLIS'));
        $this->assertContains('Digital Archiving', $this->skillsFor('BLIS'));
    }

    /** And is not offered what it does not. */
    public function test_a_programme_is_not_offered_another_subject(): void
    {
        $this->assertNotContains('Cataloguing & Classification', $this->skillsFor('BSCS'));
        $this->assertNotContains('Library Systems', $this->skillsFor('BSIT'));
        $this->assertNotContains('Embedded Systems', $this->skillsFor('BLIS'));
        $this->assertNotContains('Machine Learning', $this->skillsFor('BLIS'));
    }

    /** Skills genuinely shared between programmes are offered to each. */
    public function test_a_skill_two_programmes_teach_belongs_to_both(): void
    {
        foreach (['BSCS', 'BSIT', 'BSIS', 'BLIS'] as $code) {
            $this->assertContains('Database Systems', $this->skillsFor($code), "{$code} lost Database Systems.");
        }

        $this->assertSame(['BSCS', 'BSIT'], SkillCatalog::programsFor('Embedded Systems'));
    }

    /**
     * An expert who has not said which programmes they take is offered
     * everything.
     *
     * Narrowing to nothing would leave them with the research skills and no
     * technical ones at all, which is worse than an unfiltered list.
     */
    public function test_naming_no_programme_offers_the_whole_catalogue(): void
    {
        $this->assertSame(SkillCatalog::groups(), SkillCatalog::groupsForPrograms([]));
        $this->assertSame(SkillCatalog::groups(), SkillCatalog::groupsForPrograms(['NOT-A-PROGRAM']));
    }

    /** Two programmes together offer the union of what each does. */
    public function test_two_programmes_together_offer_both_their_skills(): void
    {
        $both = $this->skillsFor('BLIS');
        $this->assertNotContains('Machine Learning', $both);

        $merged = array_merge(...array_values(SkillCatalog::groupsForPrograms(['BLIS', 'BSCS'])));

        $this->assertContains('Machine Learning', $merged);
        $this->assertContains('Cataloguing & Classification', $merged);
    }
}
