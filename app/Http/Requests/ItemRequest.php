<?php

namespace App\Http\Requests;

use App\Enums\ItemCategory;
use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item === null || (bool) $this->user('api')?->can('update', $item);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')->where('user_id', $this->ownerId())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(ItemCategory::class)],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'status' => ['nullable', Rule::enum(ItemStatus::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * The shop must belong to the item's owner: the requester on create, the current owner on update
     * (so an admin edit cannot move an item into the admin's own shop).
     */
    private function ownerId(): int
    {
        $item = $this->route('item');

        return $item instanceof Item ? $item->shop->user_id : $this->user('api')->id;
    }
}
