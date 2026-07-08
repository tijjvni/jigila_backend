<?php

namespace App\Models;

use App\Enums\InvoiceType;
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
    ];

    protected function casts(): array
    {
        return [
            'type'     => InvoiceType::class,
            'amount'   => 'decimal:2',
            'paid_at'  => 'datetime',
            'due_date' => 'date',
            'metadata' => 'array',
        ];
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
