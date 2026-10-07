<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A remark on a diagram page (.drawio, .vsdx) can be tied to the shapes or
 * arrows it is about, not to a spot on the picture. shape_ref keeps which
 * file, page and cells those are, and what they said, so the remark can find
 * the same cells in the next version even when they moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_remarks', function (Blueprint $table) {
            $table->json('shape_ref')->nullable()->after('rects');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_remarks', function (Blueprint $table) {
            $table->dropColumn('shape_ref');
        });
    }
};
