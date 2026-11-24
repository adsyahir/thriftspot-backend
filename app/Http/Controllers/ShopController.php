<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopRequest;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(50, $request->integer('per_page', 12)));

        return ShopResource::collection(
            Shop::query()->latest('id')->paginate($perPage)
        );
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(50, $request->integer('per_page', 12)));

        return ShopResource::collection(
            $request->user('api')->shops()->latest('id')->paginate($perPage)
        );
    }

    public function show(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
    }

    public function store(ShopRequest $request): JsonResponse
    {
        $shop = $request->user('api')->shops()->create($request->validated());

        return (new ShopResource($shop))->response()->setStatusCode(201);
    }

    public function update(ShopRequest $request, Shop $shop): ShopResource
    {
        $shop->update($request->validated());

        return new ShopResource($shop);
    }

    public function destroy(Shop $shop): JsonResponse
    {
        $this->authorize('delete', $shop);

        $shop->items->each->deleteImage();
        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully']);
    }
}
