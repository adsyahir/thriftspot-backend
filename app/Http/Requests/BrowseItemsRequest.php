<?php

namespace App\Http\Requests;

use App\Enums\ItemCategory;
use App\Enums\MalaysianState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(ItemCategory::class)],
            'state' => ['nullable', Rule::enum(MalaysianState::class)],
            'shop_id' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer'],
        ];
    }
}
