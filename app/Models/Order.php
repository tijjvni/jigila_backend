<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 */
class Order extends Model
{
    use HasFactory;

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
}
