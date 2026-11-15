<?php

use App\Models\ReportTable;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Team master lists saved before the report had a Student ID column get one, just before Student. */
    public function up(): void
    {
        foreach (ReportTable::where('name', 'Team master list')->get() as $table) {
            $columns = collect($table->columns);
            if ($columns->contains(fn ($c) => ($c['key'] ?? '') === 'id' || in_array('id', $c['sources'] ?? [], true))) {
                continue;
            }

            $at = $columns->search(fn ($c) => ($c['key'] ?? '') === 'student');
            $columns->splice($at === false ? $columns->count() : $at, 0, [['key' => 'id', 'label' => ReportTable::FIELDS['id'], 'sources' => ['id'], 'separator' => ' · ']]);
            $table->update(['columns' => $columns->values()->all()]);
        }
    }

    public function down(): void
    {
        // The column stays: a CaPSA can take it off with Edit columns.
    }
};
