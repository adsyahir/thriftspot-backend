<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Http\Requests\ItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function mine(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'shop_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ItemStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $perPage = max(1, min(50, $request->integer('per_page', 12)));

        $items = Item::query()
            ->with('shop')
            ->whereHas('shop', fn ($q) => $q->where('user_id', $request->user('api')->id))
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->whereLike('name', '%'.Item::escapeLike($request->string('search')->toString()).'%'))
            ->latest('id')
            ->paginate($perPage);

        return ItemResource::collection($items);
    }

    public function showMine(Item $item): ItemResource
    {
        $this->authorize('view', $item);

        return new ItemResource($item->load('shop'));
    }

    public function store(ItemRequest $request): JsonResponse
    {
        $data = $request->safe()->except('image');
        $data['status'] ??= ItemStatus::Active->value;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }

        $item = Item::create($data);

        return (new ItemResource($item->load('shop')))->response()->setStatusCode(201);
    }

    public function update(ItemRequest $request, Item $item): ItemResource
    {
        $data = $request->safe()->except('image');
        $data['status'] ??= $item->status;
        $oldPath = $item->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }

        $item->update($data);

        if ($request->hasFile('image') && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return new ItemResource($item->load('shop'));
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $item->delete();
        $item->deleteImage();

        return response()->json(['message' => 'Item deleted successfully']);
    }
}
