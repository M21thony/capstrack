<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'team';

    protected $primaryKey = 'team_id';

    public $timestamps = false;

    protected $fillable = [
        'team_name',
        'project_title',
        'tags',
        'team_status',
        'leader_id',
        'is_confirmed',
        'member_count',
        'selected_pre_proposal_id',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'member_count' => 'integer',
    ];

    // Some legacy rows have double-encoded JSON in `tags` (e.g. the literal
    // string "[]" instead of an actual empty array), which crashes any
    // foreach() over $team->tags. Decode defensively until we get an array.
    protected function tags(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                while (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return [];
                    }
                    $value = $decoded;
                }

                return is_array($value) ? $value : [];
            },
            set: fn ($value) => json_encode(is_array($value) ? $value : []),
        );
    }

    public function members()
    {
        return $this->hasManyThrough(
            Student::class,
            StudentSchoolYear::class,
            'team_id',
            'student_id',
            'team_id',
            'student_id'
        );
    }

    public function leader()
    {
        return $this->belongsTo(Student::class, 'leader_id', 'student_id');
    }

    public function expertAssignments()
    {
        return $this->hasMany(TeamExpertAssignment::class, 'team_id', 'team_id');
    }

    public function defenseSchedules()
    {
        return $this->hasMany(DefenseSchedule::class, 'team_id', 'team_id');
    }

    public function preProposals()
    {
        return $this->hasMany(CapstonePreProposal::class, 'team_id', 'team_id')->orderBy('slot');
    }

    public function selectedPreProposal()
    {
        return $this->belongsTo(CapstonePreProposal::class, 'selected_pre_proposal_id');
    }

    public static function nameExists(string $name, ?int $ignoreTeamId = null): bool
    {
        return static::query()
            ->when($ignoreTeamId, fn ($query) => $query->where('team_id', '!=', $ignoreTeamId))
            ->whereRaw('LOWER(team_name) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }
}
