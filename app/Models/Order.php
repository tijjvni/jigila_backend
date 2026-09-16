<?php

namespace App\Models;

use App\Enums\AuctionSource;
use App\Enums\OrderStatus;
use App\Enums\ShippingLine;
use App\Enums\ShippingType;
use App\Enums\VehicleCondition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property string|null $vessel_name
 * @property string|null $container_number
 * @property string|null $shipping_tracking_number
 * @property ShippingLine|null $shipping_line
 * @property ShippingType|null $shipping_type
 * @property string|null $current_vessel_location
 * @property Carbon|null $port_received_at
 * @property Carbon|null $eta_start
 * @property Carbon|null $eta_end
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $cancelled_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Order extends Model
{
    use HasFactory, Prunable, SoftDeletes;

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
        // Admin-only shipping details — reachable only through
        // UpdateOrderShippingRequest on the admin route.
        'vessel_name',
        'container_number',
        'shipping_tracking_number',
        'shipping_line',
        'shipping_type',
        'current_vessel_location',
        'port_received_at',
        'eta_start',
        'eta_end',
        // Condition the export port authority actually confirmed, which can
        // differ from the `condition` the vehicle was booked under.
        'port_condition',
        'port_condition_confirmed_at',
        'port_condition_note',
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
            'shipping_line'     => ShippingLine::class,
            'shipping_type'     => ShippingType::class,
            'port_received_at'  => 'date',
            'eta_start'         => 'date',
            'eta_end'           => 'date',
            'cancelled_at'      => 'datetime',

            'port_condition'              => VehicleCondition::class,
            'port_condition_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Cancellation is free within this window; afterwards it depends on
     * whether the order has passed an operational milestone (BUG-064).
     */
    public function withinFreeCancellationWindow(): bool
    {
        return $this->created_at->diffInMinutes(now()) < config('orders.free_cancellation_minutes', 60);
    }

    /**
     * Once Jigila has committed money or moved the vehicle, the customer can no
     * longer self-cancel — they have to raise a support ticket instead.
     */
    public function passedOperationalMilestone(): bool
    {
        return !in_array($this->status, [OrderStatus::Pending, OrderStatus::Processing], true);
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

    public function documents(): HasMany
    {
        return $this->hasMany(OrderDocument::class)->latest();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }
}
