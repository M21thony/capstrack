<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One saved report-builder table of a CaPSA/TSA: its columns, filters and row mode. */
class ReportTable extends Model
{
    protected $fillable = ['expert_id', 'name', 'mode', 'columns', 'filters'];

    protected $casts = ['columns' => 'array', 'filters' => 'array'];

    public const MODES = ['students', 'teams', 'experts'];

    public const SEPARATORS = [' · ', ' – ', ', ', "\n"];

    /** Every field a column can show, key => heading. Expert rows fill only the last four plus team/course/section. */
    public const FIELDS = [
        'id' => 'Student ID', 'student' => 'Student', 'team' => 'Team', 'course' => 'Course', 'section' => 'Section',
        'capa' => 'CaPA', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian', 'title' => 'Project title',
        'year' => 'Year level', 'progress' => 'Current milestone', 'completed' => 'Requirements completed', 'overdue' => 'Overdue items',
        'defense' => 'Latest defense', 'score' => 'Latest defense average', 'verdict' => 'Latest defense verdict',
        'titlegrade' => 'Title Defense average', 'titlescores' => 'Title Defense score per panelist', 'titleverdict' => 'Title Defense verdict',
        'pregrade' => 'Pre-oral average', 'prescores' => 'Pre-oral score per panelist', 'preverdict' => 'Pre-oral verdict',
        'finalgrade' => 'Final Defense average', 'finalscores' => 'Final Defense score per panelist', 'finalverdict' => 'Final Defense verdict',
        'avggrade' => 'Average of all defenses',
        'date' => 'Defense date', 'time' => 'Defense time', 'room' => 'Room', 'chair' => 'Panel chair', 'panel' => 'Panel members',
        'fee' => 'Fee paid (PHP)', 'orno' => 'OR number', 'paidon' => 'Payment checked on', 'receiptstatus' => 'Receipt status', 'paid' => 'Students paid',
        'expert' => 'Expert', 'email' => 'Email', 'role' => 'Expert role', 'load' => 'Teams assigned',
    ];

    /** The same fields under the headings the "Add columns" box lists them by. */
    public const GROUPS = [
        'Student' => ['id', 'student', 'course', 'year', 'section'],
        'Team' => ['team', 'title', 'capa', 'consultant', 'grammarian'],
        'Requirements' => ['progress', 'completed', 'overdue'],
        'Title Defense' => ['titlegrade', 'titlescores', 'titleverdict'],
        'Pre-oral Defense' => ['pregrade', 'prescores', 'preverdict'],
        'Final Defense' => ['finalgrade', 'finalscores', 'finalverdict', 'avggrade'],
        'Latest defense and its schedule' => ['defense', 'score', 'verdict', 'date', 'time', 'room', 'chair', 'panel'],
        'Fees' => ['fee', 'orno', 'paidon', 'receiptstatus', 'paid'],
        'Expert' => ['expert', 'email', 'role', 'load'],
    ];

    /** Ready-made reports on Reports Home. */
    public const TEMPLATES = [
        ['key' => 'progress', 'name' => 'Team progress', 'group' => 'Teams', 'description' => 'One line per team: current milestone, requirements completed, overdue items.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'title', 'progress', 'completed', 'overdue']],
        ['key' => 'master', 'name' => 'Team master list', 'group' => 'Teams', 'description' => 'One line per team: students and their student IDs, project title, CaPA, consultant, grammarian.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'id', 'student', 'title', 'capa', 'consultant', 'grammarian']],
        ['key' => 'titledefense', 'name' => 'Title Defense scores', 'group' => 'Defenses', 'description' => 'One line per team: each panelist\'s score, the average, and the verdict.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'titlescores', 'titlegrade', 'titleverdict']],
        ['key' => 'results', 'name' => 'Defense results', 'group' => 'Defenses', 'description' => 'One line per team: its latest defense, the panel average, and the verdict.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'defense', 'score', 'verdict']],
        ['key' => 'fees', 'name' => 'Fee collection', 'group' => 'Teams', 'description' => 'One line per student: amount paid, OR number, date checked, receipt status.', 'mode' => 'students', 'fields' => ['id', 'student', 'team', 'course', 'section', 'fee', 'orno', 'paidon', 'receiptstatus']],
        ['key' => 'grades', 'name' => 'Student grades', 'group' => 'Students', 'description' => 'One line per student: Title, Pre-oral and Final Defense averages.', 'mode' => 'students', 'fields' => ['id', 'student', 'course', 'section', 'team', 'titlegrade', 'pregrade', 'finalgrade', 'avggrade']],
        ['key' => 'load', 'name' => 'Expert load', 'group' => 'Experts', 'description' => 'One line per expert: role and how many teams they handle.', 'mode' => 'experts', 'fields' => ['expert', 'role', 'load', 'team', 'course']],
        ['key' => 'experts', 'name' => 'Expert list', 'group' => 'Experts', 'description' => 'One line per expert: email, role, and assigned teams.', 'mode' => 'experts', 'fields' => ['expert', 'email', 'role', 'team', 'course', 'section']],
        ['key' => 'schedule', 'name' => 'Defense schedule', 'group' => 'Defenses', 'description' => 'One line per team: defense date, time, room, and panel.', 'mode' => 'teams', 'fields' => ['team', 'course', 'section', 'defense', 'date', 'time', 'room', 'chair', 'panel']],
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
