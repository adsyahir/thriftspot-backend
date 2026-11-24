<?php

namespace App\Models;

use App\Enums\ItemCategory;
use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'name',
        'description',
        'category',
        'condition',
        'status',
        'price',
        'image_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ItemCategory::class,
            'condition' => ItemCondition::class,
            'status' => ItemStatus::class,
            'price' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    /**
     * Escape LIKE wildcards so user input matches literally.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
