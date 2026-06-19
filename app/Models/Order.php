<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OrderAuditLog;

/**
 * @property int $id
 * @property int $user_id
 * @property string $vin
 * @property string|null $stock_id
 * @property string $auction_source
 * @property string $condition
 * @property bool $already_purchased
 * @property string|null $bid_price
 * @property string|null $vehicle_stock_no
 * @property string|null $buyer_no
 * @property string|null $buyer_code
 * @property array|null $services
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Order extends Model
{
    use HasFactory, SoftDeletes, Prunable;

    protected $fillable = [
        'user_id',
        'vin',
        'stock_id',
        'auction_source',
        'condition',
        'already_purchased',
        'bid_price',
        'vehicle_stock_no',
        'buyer_no',
        'buyer_code',
        'services',
        'status',
        'pickup_location',
        'departure_port',
        'destination_port',
        'bid_status',
        'out_bid_price',
        'vehicle_type',
    ];

    protected function casts(): array
    {
        return [
            'already_purchased' => 'boolean',
            'services' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(OrderAuditLog::class);
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }
}
