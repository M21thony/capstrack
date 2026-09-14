<?php

namespace App\Models;

use Database\Factories\FinancialRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

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

    /** 'rejected' is what the table stores; the pages call it "sent back". */
    public static function statusKey(?string $status): string
    {
        return match ($status) {
            'verified' => 'verified',
            'rejected' => 'sent_back',
            default => 'pending',
        };
    }

    /**
     * A member's receipt for one fee - their first, or a new photo in place
     * of one still waiting or sent back. A verified receipt is final.
     *
     * No receipt number: the CaPSA reads it off the photo.
     */
    public static function submitFor(Student $student, ExpensePost $post, UploadedFile $file): self
    {
        $current = self::where('student_id', $student->student_id)
            ->where('expense_post_id', $post->id)
            ->orderByDesc('id')
            ->first();

        if ($current?->f_record_status === 'verified') {
            throw ValidationException::withMessages([
                'receipt_image' => 'Your receipt for this fee is already verified. There is nothing to replace.',
            ]);
        }

        $values = [
            'receipt_image' => $file->store('receipts', 'public'),
            'f_record_status' => 'pending',
            'rejection_reason' => null,
            'verified_at' => null,
            'verified_by' => null,
            'uploaded_at' => now(),
            'payment_for' => $post->title,
            'amount' => $post->amount,
            'description' => $post->description,
        ];

        if ($current) {
            $current->update($values);

            return $current;
        }

        return self::create(['student_id' => $student->student_id, 'expense_post_id' => $post->id] + $values);
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
