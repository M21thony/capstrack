<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A remark could only ever be made by selecting words, so a diagram - which
 * has no words to select when it is a picture - could not be marked at all.
 *
 * The annotator now also draws a box over part of a page, or drops a pin on
 * one spot. mark_type says which of the three a remark is; every existing row
 * is a text remark. A box can be several boxes (two entities that do not
 * match), kept in rects - top/left/width/height stay the box around all of
 * them, so everything that already reads those four keeps working. A remark
 * on a figure records which one ("Figure 3.2") and what kind of diagram it
 * is, so the CLF can say which figure it is about.
 *
 * The review keeps which figures the reviewer looked at and found nothing to
 * say about, so the Figures list can show every figure as reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_remarks', function (Blueprint $table) {
            $table->string('mark_type', 10)->default('text')->after('selected_text');
            $table->json('rects')->nullable()->after('mark_type');
            $table->string('figure_label', 40)->nullable()->after('rects');
            $table->string('diagram_type', 20)->nullable()->after('figure_label');
        });

        Schema::table('capstone_document_review', function (Blueprint $table) {
            $table->json('figures_checked')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pdf_remarks', function (Blueprint $table) {
            $table->dropColumn(['mark_type', 'rects', 'figure_label', 'diagram_type']);
        });

        Schema::table('capstone_document_review', function (Blueprint $table) {
            $table->dropColumn('figures_checked');
        });
    }
};
