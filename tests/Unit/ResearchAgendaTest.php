<?php

namespace Tests\Unit;

use App\Support\ResearchAgenda;
use PHPUnit\Framework\TestCase;

class ResearchAgendaTest extends TestCase
{
    public function test_bullets_new_lines_and_semicolons_each_start_an_item(): void
    {
        $this->assertSame(
            ['Applications and Systems Development', 'e-Services', 'Data Science', 'Health', 'Education', 'Agriculture'],
            ResearchAgenda::items(['• Applications and Systems Development • e-Services ● Data Science', "Health\nEducation; Agriculture"]),
        );
    }

    public function test_numbering_and_dashes_are_taken_off_but_hyphenated_words_are_kept(): void
    {
        $this->assertSame(['Smart Cities', 'e-Governance', 'Health'], ResearchAgenda::items(['- Smart Cities', '1. e-Governance', 'a) Health']));
    }

    public function test_repeats_and_blanks_are_dropped(): void
    {
        $this->assertSame(['e-Services'], ResearchAgenda::items(['e-Services', ' E-SERVICES ', '', '•', null]));
    }

    public function test_one_line_of_text_is_read_as_a_list(): void
    {
        $this->assertSame(['A', 'B'], ResearchAgenda::items('A; B'));
    }
}
