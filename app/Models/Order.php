<?php

namespace App\Models;

use App\Enums\AuctionSource;
use App\Enums\OrderStatus;
use App\Enums\VehicleCondition;
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
 * @property AuctionSource $auction_source
 * @property VehicleCondition $condition
 * @property bool $already_purchased
 * @property string|null $bid_price
 * @property string|null $vehicle_stock_no
 * @property string|null $buyer_no
 * @property string|null $buyer_code
 * @property array|null $services
 * @property string|null $vehicle_type
 * @property string|null $pickup_location
 * @property string|null $departure_port
 * @property string|null $destination_port
 * @property OrderStatus $status
 * @property string|null $bid_status
 * @property string|null $out_bid_price
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
        'pickup_location',
        'departure_port',
        'destination_port',
        'vehicle_type',
    ];

    protected function casts(): array
    {
        return [
            'already_purchased' => 'boolean',
            'bid_price'         => 'decimal:2',
            'services'          => 'array',
            'auction_source'    => AuctionSource::class,
            'condition'         => VehicleCondition::class,
            'status'            => OrderStatus::class,
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
