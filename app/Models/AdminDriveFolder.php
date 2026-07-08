<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminDriveFolder extends Model
{
    protected $fillable = [
        'name',
        'drive_folder_id',
        'created_by',
        'team_id',
        'parent_folder_id',
    ];
}