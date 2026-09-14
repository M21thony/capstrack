<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CapstoneDocument extends Model
{
    protected $table = 'capstone_documents';

    protected $primaryKey = 'file_id';

    protected $fillable = [
        'student_id',
        'file_name',
        'file_path',
        'file_version',
        'document_status',
        'chapter',
        'deliverable_id',
    ];

    public function reviews()
    {
        return $this->hasMany(CapstoneDocumentReview::class, 'file_id', 'file_id');
    }

    /** The checklist deliverable this file was sent for, if any. */
    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    /**
     * Which round this upload is: the first submission, then the 1st, 2nd...
     * revision. Read from the version, which counts uploads of the same
     * requirement (or the same chapter, for files that name none).
     */
    public function roundLabel(): string
    {
        return self::round(max(1, (int) $this->file_version) - 1);
    }

    /** What the team is asked for if this upload is sent back. */
    public function nextRoundLabel(): string
    {
        return self::round(max(1, (int) $this->file_version));
    }

    /**
     * The team's earlier uploads of the same requirement, newest first - what
     * a reviewer compares this one against to see what changed. Matched the
     * way versions are counted: the checklist line, else the chapter, else
     * the file name.
     *
     * @return Collection<int, self>
     */
    public function earlierVersions(): Collection
    {
        $teamId = StudentSchoolYear::where('student_id', $this->student_id)->whereNotNull('team_id')->value('team_id');
        $students = $teamId ? StudentSchoolYear::where('team_id', $teamId)->pluck('student_id') : collect([$this->student_id]);

        return self::whereIn('student_id', $students)
            ->where('file_id', '!=', $this->file_id)
            ->where('file_version', '<', (int) $this->file_version)
            ->when(
                $this->deliverable_id,
                fn ($q) => $q->where('deliverable_id', $this->deliverable_id),
                fn ($q) => $this->chapter ? $q->where('chapter', $this->chapter) : $q->where('file_name', $this->file_name)
            )
            ->orderByDesc('file_version')
            ->get();
    }

    private static function round(int $revision): string
    {
        if ($revision <= 0) {
            return 'First submission';
        }

        $suffix = in_array($revision % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$revision % 10] ?? 'th');

        return $revision.$suffix.' revision';
    }
}
