<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $uploaded_by
 * @property DocumentType $type
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class OrderDocument extends Model
{
    use HasFactory, Prunable, SoftDeletes;

    protected $fillable = [
        'order_id',
        'uploaded_by',
        'type',
        'original_name',
        'path',
        'mime_type',
        'size',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }
}
