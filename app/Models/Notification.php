<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'expert_id',
        'type',
        'title',
        'message',
        'link',
        'data',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];
}
