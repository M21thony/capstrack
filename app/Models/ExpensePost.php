<?php

namespace App\Models;

use Database\Factories\ExpensePostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpensePost extends Model
{
    /** @use HasFactory<ExpensePostFactory> */
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'title',
        'course',
        'year_level',
        'amount',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'admin_id', 'expert_id');
    }
}
