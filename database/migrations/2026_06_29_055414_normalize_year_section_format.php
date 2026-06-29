<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $students = DB::table('students')
            ->whereNotNull('year_section')
            ->where('year_section', '!=', '')
            ->get(['student_id', 'year_section', 'course']);

        foreach ($students as $student) {
            $normalized = $this->normalize($student->year_section, $student->course);
            if ($normalized !== null && $normalized !== $student->year_section) {
                DB::table('students')
                    ->where('student_id', $student->student_id)
                    ->update(['year_section' => $normalized]);
            }
        }
    }

    private function normalize(string $value, ?string $course): ?string
    {
        // If already in "3rd · A" format, skip
        if (preg_match('/^(3rd|4th)\s*·\s*[A-Z]$/', $value)) {
            return null;
        }

        // Try to extract a year number (3 or 4) and a section letter
        $year = null;
        $section = null;

        // Remove middle-dot variations: "·", "•", "-", ","
        $cleaned = preg_replace('/[·•,]\s*/', ' ', $value);
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        // Try "4th Year - SECTION" or "3rd Year Section" patterns
        if (preg_match('/(\d+)\s*(?:st|nd|rd|th)?\s*Year/i', $cleaned, $m)) {
            $year = (int) $m[1];
        }

        // Try standalone year ordinal: "3rd", "4th"
        if ($year === null && preg_match('/\b(3rd|4th)\b/i', $cleaned, $m)) {
            $year = (int) preg_replace('/\D/', '', $m[1]);
        }

        // Try bare number year: "3" or "4"
        if ($year === null && preg_match('/\b([34])\b/', $cleaned, $m)) {
            $year = (int) $m[1];
        }

        // Extract section letter — look for uppercase letter at end
        // Try "BSCS-4A", "BSIT-3B" patterns
        if (preg_match('/[A-Z]+-\d*([A-Z])\s*$/', $cleaned, $m)) {
            $section = strtoupper($m[1]);
        }

        // Try standalone letter at the end: "A", "B"
        if ($section === null && preg_match('/\b([A-Z])\s*$/', $cleaned, $m)) {
            $section = strtoupper($m[1]);
        }

        if ($year === null && $section === null) {
            return null;
        }

        $year ??= 4;
        $ordinal = $year === 3 ? '3rd' : '4th';
        $section ??= 'A';

        return $ordinal.' · '.$section;
    }

    public function down(): void
    {
        // Not reversible — old formats are lost after normalization
    }
};
