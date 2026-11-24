<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Item */
class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'condition' => $this->condition,
            'status' => $this->status,
            'price' => $this->price,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'shop' => $this->whenLoaded('shop', fn () => [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'state' => $this->shop->state,
            ]),
            'views' => $this->whenHas('views', fn () => (int) $this->views),
            'created_at' => $this->created_at,
        ];
    }
}
