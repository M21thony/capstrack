<?php

use App\Models\CapstoneExpert;
use App\Support\SectionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Sections used to be added for 3rd and 4th year at once, so every CaPSA/TSA
 * looked like they ran both. The list now starts at 3rd year; the 4th-year
 * sections it made in advance are taken off it, except one a CaPSA/TSA already
 * holds. A 4th-year section with students in it is still listed - that comes
 * from the students, not from this list.
 */
return new class extends Migration
{
    public function up(): void
    {
        $held = CapstoneExpert::whereNotNull('sections')->get()->flatMap(fn ($c) => $c->sections ?? [])->all();

        SectionCatalog::store(array_values(array_filter(
            SectionCatalog::defined(),
            fn (string $label) => ! preg_match('/^[A-Z]+-4[A-Z]*$/', $label) || in_array($label, $held, true),
        )));
    }

    /** Nothing to put back: a 4th-year section returns with its students. */
    public function down(): void {}
};
