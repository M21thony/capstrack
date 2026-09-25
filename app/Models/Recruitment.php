<?php

namespace App\Models;

use Database\Factories\RecruitmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'member_count',
    ];

    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
        ];
    }

    /**
     * The roles a post is looking for, always as a list.
     *
     * Some rows hold the list JSON-encoded twice, which the plain array cast
     * read back as a string - and the Team page's implode() on it took the
     * whole page down for everyone in that section.
     */
    protected function roles(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                $roles = json_decode((string) $value, true);

                if (is_string($roles)) {
                    $roles = json_decode($roles, true);
                }

                return is_array($roles) ? array_values($roles) : [];
            },
            set: fn (mixed $value): ?string => is_array($value) ? json_encode(array_values($value)) : $value,
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
