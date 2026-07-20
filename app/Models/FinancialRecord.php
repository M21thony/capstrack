<?php

namespace App\Models;

use Database\Factories\FinancialRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialRecord extends Model
{
    /** @use HasFactory<FinancialRecordFactory> */
    use HasFactory;

    protected $table = 'financial_records';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'expense_post_id',
        'receipt_image',
        'receipt_number',
        'f_record_status',
        'payment_for',
        'amount',
        'description',
        'rejection_reason',
        'uploaded_at',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function expensePost(): BelongsTo
    {
        return $this->belongsTo(ExpensePost::class);
    }
}
