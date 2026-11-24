<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseTest extends TestCase
{
    use RefreshDatabase;

    private function shop(array $attributes = []): Shop
    {
        return Shop::factory()->for(User::factory()->create())->create($attributes);
    }

    public function test_lists_only_active_items(): void
    {
        $shop = $this->shop();
        $active = Item::factory()->for($shop)->create();
        Item::factory()->for($shop)->create(['status' => 'sold']);
        Item::factory()->for($shop)->create(['status' => 'hidden']);

        $this->getJson('/api/items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.shop.id', $shop->id)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_filters_by_category_state_and_price(): void
    {
        $penang = $this->shop(['state' => 'penang']);
        $perak = $this->shop(['state' => 'perak']);
        $match = Item::factory()->for($penang)->create(['category' => 'fashion', 'price' => 30]);
        Item::factory()->for($penang)->create(['category' => 'home', 'price' => 30]);
        Item::factory()->for($perak)->create(['category' => 'fashion', 'price' => 30]);
        Item::factory()->for($penang)->create(['category' => 'fashion', 'price' => 200]);

        $this->getJson('/api/items?category=fashion&state=penang&min_price=10&max_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);

        $this->getJson("/api/items?shop_id={$perak->id}")->assertJsonCount(1, 'data');
    }

    public function test_search_is_case_insensitive_and_escapes_wildcards(): void
    {
        $shop = $this->shop(['name' => 'Retro Hub']);
        Item::factory()->for($shop)->create(['name' => 'Vintage Denim']);
        $literal = Item::factory()->for($shop)->create(['name' => '50% off bag']);
        Item::factory()->for($shop)->create(['name' => '500 sleeve bag']);

        Item::factory()->for($shop)->create(['name' => 'Wow! sale_item']);

        $this->getJson('/api/items?search=denim')->assertJsonCount(1, 'data');
        $this->getJson('/api/items?search='.urlencode('wow!'))->assertJsonCount(1, 'data');
        $this->getJson('/api/items?search='.urlencode('sale_item'))->assertJsonCount(1, 'data');
        $this->getJson('/api/items?search='.urlencode('sale-item'))->assertJsonCount(0, 'data');
        $this->getJson('/api/items?search=retro')->assertJsonCount(4, 'data');

        $this->getJson('/api/items?search='.urlencode('50%'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->id);
    }

    public function test_sorts_by_price(): void
    {
        $shop = $this->shop();
        Item::factory()->for($shop)->create(['price' => 20]);
        Item::factory()->for($shop)->create(['price' => 5]);
        Item::factory()->for($shop)->create(['price' => 90]);

        $this->assertSame(['5.00', '20.00', '90.00'], $this->getJson('/api/items?sort=price_asc')->json('data.*.price'));
        $this->assertSame(['90.00', '20.00', '5.00'], $this->getJson('/api/items?sort=price_desc')->json('data.*.price'));
    }

    public function test_invalid_category_and_sort_return_422(): void
    {
        $this->getJson('/api/items?category=cars')->assertStatus(422)->assertJsonValidationErrors(['category']);
        $this->getJson('/api/items?sort=random')->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    public function test_per_page_is_clamped(): void
    {
        Item::factory()->for($this->shop())->count(2)->create();

        $this->getJson('/api/items?per_page=1000')->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->getJson('/api/items?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/items')->assertJsonPath('meta.per_page', 12);
    }

    public function test_hidden_item_is_404_but_sold_is_visible(): void
    {
        $shop = $this->shop();
        $sold = Item::factory()->for($shop)->create(['status' => 'sold']);
        $hidden = Item::factory()->for($shop)->create(['status' => 'hidden']);

        $this->getJson("/api/items/{$sold->id}")->assertOk()->assertJsonPath('data.status', 'sold');
        $this->getJson("/api/items/{$hidden->id}")->assertNotFound();
        $this->postJson("/api/items/{$hidden->id}/view")->assertNotFound();
    }

    public function test_view_increments_same_day_row(): void
    {
        $item = Item::factory()->for($this->shop())->create();

        $this->postJson("/api/items/{$item->id}/view")->assertNoContent();
        $this->postJson("/api/items/{$item->id}/view")->assertNoContent();

        $this->assertDatabaseCount('item_view_days', 1);
        $this->assertDatabaseHas('item_view_days', ['item_id' => $item->id, 'count' => 2]);
    }

    public function test_owner_view_is_not_counted(): void
    {
        $owner = $this->actingAsUser();
        $item = Item::factory()->for(Shop::factory()->for($owner))->create();

        $this->postJson("/api/items/{$item->id}/view")->assertNoContent();

        $this->assertDatabaseCount('item_view_days', 0);
    }

    public function test_shops_can_be_searched_and_filtered_by_state(): void
    {
        $this->shop(['name' => 'Retro Hub', 'state' => 'penang']);
        $this->shop(['name' => 'Other Store', 'state' => 'penang']);
        $this->shop(['name' => 'Retro Two', 'state' => 'perak']);

        $this->getJson('/api/shops?search=RETRO')->assertJsonCount(2, 'data');
        $this->getJson('/api/shops?search=retro&state=penang')->assertJsonCount(1, 'data');
    }
}
