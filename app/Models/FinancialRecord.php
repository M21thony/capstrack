<?php

namespace App\Models;

use Database\Factories\FinancialRecordFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * A receipt a member has uploaded that their leader has not yet sent to
     * the CaPSA. Only 'pending' and later are the CaPSA's to see.
     */
    public const HELD = 'held';

    /** 'rejected' is what the table stores; the pages call it "sent back". */
    public static function statusKey(?string $status): string
    {
        return match ($status) {
            'verified' => 'verified',
            'rejected' => 'sent_back',
            self::HELD => 'held',
            default => 'pending',
        };
    }

    /** Everything the CaPSA is allowed to see: nothing still with a leader. */
    public function scopeSentToCapsa(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('f_record_status')->orWhere('f_record_status', '!=', self::HELD));
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
     * of one still held by the leader or sent back. It goes to the team
     * leader, not the CaPSA: see submitTeam(). A receipt already sent to the
     * CaPSA, or verified, is not replaced.
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
                'receipt_image' => 'Your team leader already sent this receipt to the CaPSA. Wait for it to be checked.',
            ]);
        }

        $values = [
            'receipt_image' => $file->store('receipts', 'public'),
            // Typed by the student; the CaPSA checks it against the photo before verifying.
            'receipt_number' => self::normaliseOrNumber($orNumber),
            // Kept by the leader until they send the whole team's receipts.
            'f_record_status' => self::HELD,
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

    /**
     * The leader sends the team's receipts for one fee to the CaPSA together.
     *
     * Every member needs a receipt in hand first (held, or already sent or
     * verified); a member with none, or with one the CaPSA sent back, blocks
     * it. What is sent is whatever is still held - after a send-back, only
     * the replaced receipts go again.
     *
     * @return int how many receipts went to the CaPSA
     */
    public static function submitTeam(Student $leader, Team $team, ExpensePost $post): int
    {
        if ((int) $team->leader_id !== (int) $leader->student_id) {
            throw ValidationException::withMessages([
                'expense_post_id' => 'Only your team leader can send the receipts to the CaPSA.',
            ]);
        }

        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->unique();

        $latest = self::where('expense_post_id', $post->id)
            ->whereIn('student_id', $memberIds)
            ->orderByDesc('id')
            ->get()
            ->unique('student_id');

        $ready = $latest->filter(fn (self $r) => in_array($r->f_record_status, [self::HELD, 'pending', 'verified'], true));

        if ($ready->count() < $memberIds->count()) {
            $missing = $memberIds->count() - $ready->count();

            throw ValidationException::withMessages([
                'expense_post_id' => $missing.' of '.$memberIds->count().' teammates still '.($missing === 1 ? 'needs' : 'need').' to upload a receipt for this fee.',
            ]);
        }

        $held = $latest->filter(fn (self $r) => $r->f_record_status === self::HELD);

        if ($held->isEmpty()) {
            throw ValidationException::withMessages([
                'expense_post_id' => 'Everything for this fee is already with the CaPSA.',
            ]);
        }

        self::whereIn('id', $held->pluck('id'))->update(['f_record_status' => 'pending']);

        return $held->count();
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
