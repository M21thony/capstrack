<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentRole extends Model
{
    protected $table = 'student_roles';

    protected $primaryKey = 's_role_id';

    protected $fillable = [
        's_role_name',
    ];

    public function studentSchoolYears(): HasMany
    {
        return $this->hasMany(StudentSchoolYear::class, 's_role_id', 's_role_id');
    }
}
