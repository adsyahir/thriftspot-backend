<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Http\Requests\BrowseItemsRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\ItemViewDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BrowseController extends Controller
{
    public function index(BrowseItemsRequest $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(50, $request->integer('per_page', 12)));

        $items = Item::query()
            ->with('shop')
            ->where('status', ItemStatus::Active)
            ->when($request->filled('category'), fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->filled('shop_id'), fn (Builder $q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('state'), fn (Builder $q) => $q->whereHas('shop', fn (Builder $s) => $s->where('state', $request->input('state'))))
            ->when($request->filled('min_price'), fn (Builder $q) => $q->where('price', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn (Builder $q) => $q->where('price', '<=', $request->input('max_price')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = $request->string('search')->toString();

                $q->where(function (Builder $w) use ($term) {
                    Item::whereContains($w, 'name', $term);
                    Item::whereContains($w, 'description', $term, 'or');
                    $w->orWhereHas('shop', fn (Builder $s) => Item::whereContains($s, 'name', $term));
                });
            });

        match ($request->input('sort', 'newest')) {
            'price_asc' => $items->orderBy('price')->orderByDesc('id'),
            'price_desc' => $items->orderByDesc('price')->orderByDesc('id'),
            default => $items->orderByDesc('id'),
        };

        return ItemResource::collection($items->paginate($perPage));
    }

    public function show(Item $item): ItemResource
    {
        abort_if($item->status === ItemStatus::Hidden, 404);

        return new ItemResource($item->load('shop'));
    }

    public function view(Request $request, Item $item): Response
    {
        abort_if($item->status === ItemStatus::Hidden, 404);

        $viewer = auth('api')->user();

        if ($viewer === null || $item->shop->user_id !== $viewer->id) {
            ItemViewDay::upsert(
                [['item_id' => $item->id, 'viewed_on' => today()->toDateString(), 'count' => 1]],
                ['item_id', 'viewed_on'],
                ['count' => DB::raw('item_view_days.count + 1')],
            );
        }

        return response()->noContent();
    }
}
