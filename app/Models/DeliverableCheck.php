<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The CaPSA's verdict on one team's signed paper for one deliverable. */
class DeliverableCheck extends Model
{
    public const COMPLETE = 'complete';

    /** Not complete; the note says what the team has to fix before uploading again. */
    public const SENT_BACK = 'sent_back';

    protected $fillable = ['deliverable_id', 'team_id', 'status', 'note', 'file_id', 'checked_by', 'checked_at'];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
