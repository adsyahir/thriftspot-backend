<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemViewDay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_counts_only_own_and_zero_fills(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();
        $item = Item::factory()->for($shop)->create();
        Item::factory()->for($shop)->create(['status' => 'sold']);
        Item::factory()->for($shop)->create(['status' => 'hidden']);
        ItemViewDay::factory()->for($item)->create(['viewed_on' => today(), 'count' => 4]);
        ItemViewDay::factory()->for($item)->create(['viewed_on' => today()->subDays(2), 'count' => 3]);
        ItemViewDay::factory()->for($item)->create(['viewed_on' => today()->subDays(40), 'count' => 10]);

        $stranger = Item::factory()->for(Shop::factory()->for(User::factory()->create()))->create();
        ItemViewDay::factory()->for($stranger)->create(['viewed_on' => today(), 'count' => 99]);

        $response = $this->getJson('/api/my/analytics?days=7')->assertOk();

        $response->assertJsonPath('data.totals', [
            'shops' => 1, 'items' => 3, 'active_items' => 1, 'sold_items' => 1, 'views' => 17,
        ]);
        $daily = $response->json('data.daily');
        $this->assertCount(7, $daily);
        $this->assertSame(today()->toDateString(), $daily[6]['date']);
        $this->assertSame(today()->subDays(6)->toDateString(), $daily[0]['date']);
        $this->assertSame(4, $daily[6]['views']);
        $this->assertSame(3, $daily[4]['views']);
        $this->assertSame(0, $daily[5]['views']);
    }

    public function test_analytics_days_are_clamped(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/my/analytics?days=1')->assertJsonCount(7, 'data.daily');
        $this->getJson('/api/my/analytics?days=500')->assertJsonCount(90, 'data.daily');
        $this->getJson('/api/my/analytics')->assertJsonCount(30, 'data.daily');
    }

    public function test_analytics_top_items_ordered_by_views(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();
        $low = Item::factory()->for($shop)->create(['name' => 'Low']);
        $high = Item::factory()->for($shop)->create(['name' => 'High']);
        $none = Item::factory()->for($shop)->create(['name' => 'None']);
        ItemViewDay::factory()->for($low)->create(['viewed_on' => today(), 'count' => 2]);
        ItemViewDay::factory()->for($high)->create(['viewed_on' => today(), 'count' => 5]);
        ItemViewDay::factory()->for($high)->create(['viewed_on' => today()->subDay(), 'count' => 1]);

        $top = $this->getJson('/api/my/analytics')->json('data.top_items');

        $this->assertSame([$high->id, $low->id], array_column($top, 'id'));
        $this->assertSame([6, 2], array_column($top, 'views'));
        $this->assertNotContains($none->id, array_column($top, 'id'));
    }

    public function test_guest_cannot_view_analytics(): void
    {
        $this->getJson('/api/my/analytics')->assertUnauthorized();
    }
}
