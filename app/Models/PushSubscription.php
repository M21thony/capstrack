<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One browser that has said "Allow" to CapsTrack's notifications.
 *
 * A person has one row per browser per machine, so the same expert on a
 * laptop and a desktop is two rows and gets the notification on both.
 */
class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'expert_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    /** The endpoint is too long for a MyISAM unique index; its hash is not. */
    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
