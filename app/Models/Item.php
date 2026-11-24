<?php

namespace App\Models;

use App\Enums\ItemCategory;
use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function viewDays(): HasMany
    {
        return $this->hasMany(ItemViewDay::class);
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    /**
     * Case-insensitive "contains" match with %, _ and ! taken literally.
     *
     * Declares the escape character explicitly because SQLite has no default one (Postgres does),
     * and lower() on both sides keeps matching case-insensitive on both. The escape character is
     * "!" rather than a backslash: PDO's pgsql placeholder parser reads `'\'` as an unterminated
     * string and then loses the bound parameters.
     */
    public static function whereContains(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));

        return $query->whereRaw("lower({$wrapped}) like ? escape '!'", ['%'.$escaped.'%'], $boolean);
    }
}
