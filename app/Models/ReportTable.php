<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One saved report-builder table of a CaPSA: its columns, filters and row mode. */
class ReportTable extends Model
{
    protected $fillable = ['expert_id', 'name', 'mode', 'columns', 'filters'];

    protected $casts = ['columns' => 'array', 'filters' => 'array'];

    public const MODES = ['students', 'teams', 'experts'];

    public const SEPARATORS = [' · ', ' – ', ', ', "\n"];

    /** Every field a column can show, key => heading. Expert rows fill only the last three plus team/course/section. */
    public const FIELDS = [
        'id' => 'Student ID', 'student' => 'Student', 'team' => 'Team', 'course' => 'Course', 'section' => 'Section',
        'capa' => 'CaPA', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian', 'title' => 'Project title',
        'year' => 'Year level', 'progress' => 'Progress', 'completed' => 'Requirements completed', 'overdue' => 'Overdue items',
        'defense' => 'Defense type', 'score' => 'Panel average', 'verdict' => 'Verdict',
        'date' => 'Defense date', 'time' => 'Defense time', 'room' => 'Room', 'panel' => 'Panel members',
        'fee' => 'Fee amount (PHP)', 'receiptstatus' => 'Receipt status',
        'expert' => 'Expert', 'role' => 'Expert role', 'load' => 'Teams assigned',
    ];

    /** Starting layouts on Reports Home. */
    public const TEMPLATES = [
        ['key' => 'progress', 'name' => 'Team progress', 'group' => 'Teams', 'description' => 'Milestones, completed requirements, and overdue items.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'title', 'progress', 'completed', 'overdue']],
        ['key' => 'master', 'name' => 'Team master list', 'group' => 'Teams', 'description' => 'Teams, members, project titles, and assigned experts.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'student', 'title', 'capa', 'consultant', 'grammarian']],
        ['key' => 'results', 'name' => 'Defense results', 'group' => 'Defenses', 'description' => 'Defense type, team verdicts, and panel scores.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'defense', 'score', 'verdict']],
        ['key' => 'fees', 'name' => 'Fee collection', 'group' => 'Teams', 'description' => 'Student receipts, checked amounts, and collection status.', 'mode' => 'students', 'fields' => ['id', 'student', 'team', 'course', 'section', 'fee', 'receiptstatus']],
        ['key' => 'grades', 'name' => 'Student grades', 'group' => 'Students', 'description' => 'Student IDs, names, and recorded defense grades.', 'mode' => 'students', 'fields' => ['id', 'student', 'course', 'section', 'team', 'defense', 'score']],
        ['key' => 'load', 'name' => 'Expert load', 'group' => 'Experts', 'description' => 'Assigned team counts for each expert and role.', 'mode' => 'experts', 'fields' => ['expert', 'role', 'load', 'team', 'course']],
        ['key' => 'experts', 'name' => 'Expert list', 'group' => 'Experts', 'description' => 'Expert names, roles, and assigned teams.', 'mode' => 'experts', 'fields' => ['expert', 'role', 'team', 'course', 'section']],
        ['key' => 'schedule', 'name' => 'Defense schedule', 'group' => 'Defenses', 'description' => 'Defense dates, times, rooms, and panel members.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'defense', 'date', 'time', 'room', 'panel']],
    ];

    /** A Home/Builder-ready copy: what the page's script works with. */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mode' => $this->mode,
            'columns' => $this->columns,
            'filters' => (object) ($this->filters ?? []),
            'updated' => $this->updated_at?->toIso8601String(),
        ];
    }
}
