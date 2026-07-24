<?php

namespace Tests\Unit;

use App\Models\Student;
use Tests\TestCase;

class ComputeSectionLabelTest extends TestCase
{
    public function test_short_format_bscs_4a(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Science in Computer Science',
            '4th · A',
        );

        $this->assertSame('BSCS-4A', $label);
    }

    public function test_long_format_bscs_4a(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Science in Computer Science',
            '4th Year · BSCS-4A',
        );

        $this->assertSame('BSCS-4A', $label);
    }

    public function test_short_format_bsit_1b(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Science in Information Technology',
            '1st · B',
        );

        $this->assertSame('BSIT-1B', $label);
    }

    public function test_long_format_bsit_1b(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Science in Information Technology',
            '1st Year · BSIT-1B',
        );

        $this->assertSame('BSIT-1B', $label);
    }

    public function test_long_format_blis_3c(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Library and Information Science',
            '3rd Year · BLIS-3C',
        );

        $this->assertSame('BLIS-3C', $label);
    }

    public function test_returns_null_for_unmapped_course(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Unicorn Studies',
            '4th · A',
        );

        $this->assertNull($label);
    }

    public function test_returns_null_for_null_course(): void
    {
        $label = Student::computeSectionLabel(null, '4th · A');
        $this->assertNull($label);
    }

    public function test_returns_null_for_null_year_section(): void
    {
        $label = Student::computeSectionLabel(
            'Bachelor of Science in Computer Science',
            null,
        );
        $this->assertNull($label);
    }

    public function test_short_and_long_format_produce_identical_labels(): void
    {
        $short = Student::computeSectionLabel(
            'Bachelor of Science in Information Systems',
            '2nd · C',
        );
        $long = Student::computeSectionLabel(
            'Bachelor of Science in Information Systems',
            '2nd Year · BSIS-2C',
        );

        $this->assertSame($short, $long);
        $this->assertSame('BSIS-2C', $short);
    }
}
