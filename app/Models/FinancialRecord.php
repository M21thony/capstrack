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
        'sent_to_dean_at',
        'sent_to_dean_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'sent_to_dean_at' => 'datetime',
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
     * The OR number as it is compared: trimmed, upper-case, single spaces.
     * "or 00123 " and "OR 00123" are the same receipt.
     */
    public static function normaliseOrNumber(string $number): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($number)));
    }

    /** Validation for the OR number a student types from their official receipt. */
    public static function orNumberRules(): array
    {
        return ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-\/#.]*$/'];
    }

    /** @return array<string, string> */
    public static function orNumberMessages(): array
    {
        return [
            'receipt_number.required' => 'Enter the OR number printed on your official receipt.',
            'receipt_number.regex' => 'Type the OR number as it is printed: letters, numbers and dashes.',
        ];
    }

    /**
     * A member's receipt for one fee - their first, or a new photo in place
     * of one the CaPSA sent back. It goes straight to the CaPSA, waiting to
     * be checked; there is no stop with the team leader. A receipt the CaPSA
     * already has, or has verified, is not replaced.
     */
    public static function submitFor(Student $student, ExpensePost $post, UploadedFile $file, string $orNumber): self
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

        if ($current?->f_record_status === 'pending') {
            throw ValidationException::withMessages([
                'receipt_image' => 'Your CaPSA already has this receipt. Wait for it to be checked.',
            ]);
        }

        $values = [
            'receipt_image' => $file->store('receipts', 'public'),
            // Typed by the student; the CaPSA checks it against the photo before verifying.
            'receipt_number' => self::normaliseOrNumber($orNumber),
            // Waiting on the CaPSA from the moment it is uploaded.
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
