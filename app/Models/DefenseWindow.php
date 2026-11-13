<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefenseWindow extends Model
{
    protected $table = 'defense_windows';

    protected $primaryKey = 'defense_window_id';

    public $timestamps = false;

    protected $fillable = [
        'defense_type',
        'program',
        'start_date',
        'end_date',
        'duration_minutes',
        'updated_by',
        'updated_at',
    ];

    protected $attributes = ['duration_minutes' => 120];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_minutes' => 'integer',
        'updated_at' => 'datetime',
    ];

    /**
     * Each stage's window for one course, keyed by stage. A course with no
     * window of its own gets the one set before windows were per course.
     *
     * @return \Illuminate\Database\Eloquent\Collection<string, self>
     */
    public static function forProgram(?string $program)
    {
        return static::where('program', $program)->orWhereNull('program')->get()
            ->sortBy(fn (self $w) => $w->program !== null)->keyBy('defense_type');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'updated_by', 'expert_id');
    }
}
