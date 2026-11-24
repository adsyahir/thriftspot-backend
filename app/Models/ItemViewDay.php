<?php

namespace App\Models;

use Database\Factories\ItemViewDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemViewDay extends Model
{
    /** @use HasFactory<ItemViewDayFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['item_id', 'viewed_on', 'count'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
            'count' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
