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

    /**
     * Stamp the time here rather than leaving it to the column's database
     * default: a database clock on UTC is eight hours behind the app's
     * Asia/Manila, which put "8 hours ago" on everything just sent.
     */
    protected static function booted(): void
    {
        static::creating(function (Notification $notification): void {
            $notification->created_at ??= now();
        });
    }
}
