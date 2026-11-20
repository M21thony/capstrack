<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A student's answer under a reviewer's remark. */
class PdfRemarkReply extends Model
{
    protected $fillable = ['remark_id', 'student_id', 'body'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /** The shape every viewer reads, also when it rides along on its remark. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'who' => trim(($this->student->f_name ?? '').' '.($this->student->l_name ?? '')) ?: 'Student',
            'body' => $this->body,
            'at' => $this->created_at?->format('M j, g:i A'),
        ];
    }
}
