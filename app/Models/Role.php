<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'role_name',
    'description',
])]
class Role extends Model
{
    protected $primaryKey = 'role_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    /**
     * The role's name as a person reads it.
     *
     * Stored names are snake_case keys - "capstone_adviser" - and putting one
     * on screen through ucfirst() left "Capstone_adviser", which the pills'
     * uppercase then made "CAPSTONE_ADVISER".
     *
     * "adviser" and "capstone_adviser" are different jobs - one advises a
     * team, the other runs the subject - so calling them "Capstone Adviser"
     * and "Capstone Subject Adviser" put a single word between two things
     * that are not alike. The team's is plain "Adviser".
     */
    public function getLabelAttribute(): string
    {
        return self::labelFor($this->role_name);
    }

    public static function labelFor(string $roleName): string
    {
        return match ($roleName) {
            'adviser' => 'Adviser',
            'capstone_adviser' => 'Capstone Subject Adviser',
            default => (string) \Illuminate\Support\Str::of($roleName)->replace('_', ' ')->title(),
        };
    }

    public function experts()
    {
        return $this->belongsToMany(CapstoneExpert::class, 'expert_roles', 'role_id', 'expert_id');
    }
}
