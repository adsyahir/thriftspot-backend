<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemViewDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $days = max(7, min(90, $request->integer('days', 30)));
        $from = today()->subDays($days - 1);

        $ownItems = Item::query()->whereHas('shop', fn ($q) => $q->where('user_id', $user->id));
        $ownViews = ItemViewDay::query()->whereIn('item_id', (clone $ownItems)->select('items.id'));

        $perDay = (clone $ownViews)
            ->where('viewed_on', '>=', $from->toDateString())
            ->selectRaw('viewed_on, sum(count) as views')
            ->groupBy('viewed_on')
            ->pluck('views', 'viewed_on')
            ->mapWithKeys(fn ($views, $date) => [substr((string) $date, 0, 10) => (int) $views]);

        $daily = collect(range(0, $days - 1))->map(function (int $offset) use ($from, $perDay) {
            $date = $from->copy()->addDays($offset)->toDateString();

            return ['date' => $date, 'views' => $perDay->get($date, 0)];
        })->all();

        $topItems = (clone $ownItems)
            ->withSum(['viewDays as views' => fn ($q) => $q->where('viewed_on', '>=', $from->toDateString())], 'count')
            ->get()
            ->filter(fn (Item $item) => (int) $item->views > 0)
            ->sortByDesc(fn (Item $item) => (int) $item->views)
            ->take(5)
            ->map(fn (Item $item) => ['id' => $item->id, 'name' => $item->name, 'views' => (int) $item->views])
            ->values()
            ->all();

        return response()->json(['data' => [
            'totals' => [
                'shops' => $user->shops()->count(),
                'items' => (clone $ownItems)->count(),
                'active_items' => (clone $ownItems)->where('status', ItemStatus::Active)->count(),
                'sold_items' => (clone $ownItems)->where('status', ItemStatus::Sold)->count(),
                'views' => (int) (clone $ownViews)->sum('count'),
            ],
            'daily' => $daily,
            'top_items' => $topItems,
        ]]);
    }
}
