<?php

namespace App\Models;

use Database\Factories\RecruitmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recruitment extends Model
{
    /** @use HasFactory<RecruitmentFactory> */
    use HasFactory;

    protected $table = 'recruitment';

    protected $primaryKey = 'recruitment_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'student_id',
        'recruitment_goal',
        'roles',
        'looking_for',
        'introduction',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
