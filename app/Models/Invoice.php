<?php

namespace App\Models;

use App\Enums\DeadlineExtensionStatus;
use App\Enums\InvoiceType;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $order_id
 * @property string $invoice_number
 * @property InvoiceType $type
 * @property string $description
 * @property float $amount
 * @property string $status
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property Carbon|null $payment_due_at
 * @property Carbon|null $overdue_at
 * @property array|null $reminder_stages_sent
 * @property string|null $late_fee_mode
 * @property float $late_fee_amount
 * @property int $late_fee_days
 * @property DeadlineExtensionStatus|null $extension_status
 * @property string|null $payment_reference
 * @property string|null $payment_url
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    use HasFactory, Prunable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_id',
        'invoice_number',
        'type',
        'description',
        'amount',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        'payment_url',
        'metadata',
        'last_reminded_at',
        'reminder_count',
        'payment_due_at',
        'deadline_hours',
        'overdue_at',
        'reminder_stages_sent',
        'late_fee_mode',
        'late_fee_rate',
        'late_fee_amount',
        'late_fee_days',
        'late_fee_accrued_at',
        'late_fee_invoiced_at',
        'extension_status',
        'extension_requested_hours',
        'extension_granted_hours',
        'extension_reason',
        'extension_requested_at',
        'extension_reviewed_at',
        'extension_reviewed_by',
        'original_payment_due_at',
        'refund_status',
        'refund_amount',
        'refund_reason',
        'refund_requested_at',
        'refund_processed_at',
        'refund_processed_by',
    ];

    protected function casts(): array
    {
        return [
            'type'                => InvoiceType::class,
            'amount'              => 'decimal:2',
            'paid_at'             => 'datetime',
            'due_date'            => 'date',
            'metadata'            => 'array',
            'last_reminded_at'    => 'datetime',
            'reminder_count'      => 'integer',

            'payment_due_at'          => 'datetime',
            'deadline_hours'          => 'integer',
            'overdue_at'              => 'datetime',
            'reminder_stages_sent'    => 'array',
            'late_fee_rate'           => 'decimal:2',
            'late_fee_amount'         => 'decimal:2',
            'late_fee_days'           => 'integer',
            'late_fee_accrued_at'     => 'datetime',
            'late_fee_invoiced_at'    => 'datetime',
            'extension_status'        => DeadlineExtensionStatus::class,
            'extension_requested_at'  => 'datetime',
            'extension_reviewed_at'   => 'datetime',
            'original_payment_due_at' => 'datetime',

            'refund_status'       => RefundStatus::class,
            'refund_amount'       => 'decimal:2',
            'refund_requested_at' => 'datetime',
            'refund_processed_at' => 'datetime',
        ];
    }

    /**
     * Unpaid invoices close enough to their deadline that a reminder stage may
     * be due (spec 4).
     *
     * This is a coarse SQL filter — it pulls everything inside the widest
     * offset, and `PaymentDeadlineService::dueStages()` decides which stages
     * have actually landed. Stage bookkeeping lives in a JSON column, which is
     * not portably queryable across SQLite, MySQL and MariaDB.
     */
    public function scopeDueForStageReminder(Builder $query): Builder
    {
        $widest = max(config('orders.payment_reminder_offsets_hours', [48, 24, 6, 0]));

        return $query->where('status', 'pending')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', now()->addHours((int) $widest));
    }

    /**
     * Unpaid invoices whose deadline has passed and that have not yet been
     * flipped to overdue.
     */
    public function scopeDueForOverdue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNull('overdue_at')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', now());
    }

    /**
     * Unpaid invoices past their deadline, whose late fee may need recomputing.
     */
    public function scopeAccruingLateFee(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<=', now());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->payment_due_at !== null
            && $this->payment_due_at->isPast();
    }

    /**
     * Hours left to pay — negative once the deadline has passed, null when the
     * invoice carries no deadline or is already settled.
     */
    public function hoursUntilDue(): ?float
    {
        if ($this->payment_due_at === null || $this->status !== 'pending') {
            return null;
        }

        return round(now()->diffInHours($this->payment_due_at, false), 2);
    }

    /**
     * What the customer actually owes: the invoice plus any late fee accrued
     * against it. The fee is billed as its own `late_fee` invoice, so this is a
     * display figure, not something a single Paystack transaction settles.
     */
    public function totalDue(): float
    {
        return round((float) $this->amount + (float) $this->late_fee_amount, 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }
}
