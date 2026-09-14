<?php

namespace App\Models;

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
            'refund_status'       => RefundStatus::class,
            'refund_amount'       => 'decimal:2',
            'refund_requested_at' => 'datetime',
            'refund_processed_at' => 'datetime',
        ];
    }

    /**
     * Unpaid invoices that are due another hour-based reminder (BUG-033).
     */
    public function scopeDueForReminder(Builder $query): Builder
    {
        $intervalHours = (int) config('orders.payment_reminder_interval_hours', 24);
        $cutoff        = now()->subHours($intervalHours);

        return $query->where('status', 'pending')
            ->where('reminder_count', '<', (int) config('orders.payment_reminder_max', 5))
            // Never remind before a full interval has elapsed — for an invoice
            // that has had no reminder yet, the clock starts at creation, so a
            // customer is not nagged seconds after the invoice is raised.
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $fresh) => $fresh
                    ->whereNull('last_reminded_at')
                    ->where('created_at', '<=', $cutoff))
                ->orWhere('last_reminded_at', '<=', $cutoff));
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
