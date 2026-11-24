<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withHeader('Accept', 'application/json');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Shop $shop, array $overrides = []): array
    {
        return array_merge([
            'shop_id' => $shop->id,
            'name' => 'Denim Jacket',
            'description' => 'Barely worn.',
            'category' => 'fashion',
            'condition' => 'good',
            'price' => '45',
        ], $overrides);
    }

    private function itemWithImage(Shop $shop): Item
    {
        $path = UploadedFile::fake()->image('old.jpg')->store('items', 'public');

        return Item::factory()->for($shop)->create(['image_path' => $path]);
    }

    public function test_user_creates_item_with_image(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();

        $response = $this->post('/api/items', $this->payload($shop, [
            'image' => UploadedFile::fake()->image('jacket.jpg'),
        ]))->assertCreated();

        $this->assertSame('45.00', $response->json('data.price'));
        $this->assertNotNull($response->json('data.image_url'));
        Storage::disk('public')->assertExists(Item::firstOrFail()->image_path);
    }

    public function test_cannot_create_item_in_another_users_shop(): void
    {
        $this->actingAsUser();
        $foreign = Shop::factory()->for(User::factory()->create())->create();

        $this->postJson('/api/items', $this->payload($foreign))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shop_id']);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_cannot_move_item_to_another_users_shop(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();
        $foreign = Shop::factory()->for(User::factory()->create())->create();
        $item = Item::factory()->for($shop)->create();

        $this->putJson("/api/items/{$item->id}", $this->payload($foreign))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shop_id']);

        $this->assertSame($shop->id, $item->fresh()->shop_id);
    }

    public function test_admin_edit_keeps_owner_shop(): void
    {
        $this->actingAsUser(role: 'admin');
        $ownerShop = Shop::factory()->for(User::factory()->create())->create();
        $item = Item::factory()->for($ownerShop)->create();

        $this->putJson("/api/items/{$item->id}", $this->payload($ownerShop, ['name' => 'Renamed']))
            ->assertOk();

        $this->assertSame('Renamed', $item->fresh()->name);
        $this->assertSame($ownerShop->id, $item->fresh()->shop_id);
    }

    public function test_rejects_non_image_and_negative_price(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();

        $this->post('/api/items', $this->payload($shop, [
            'price' => '-1',
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['image', 'price']);
    }

    public function test_replacing_image_removes_old_file(): void
    {
        $user = $this->actingAsUser();
        $item = $this->itemWithImage(Shop::factory()->for($user)->create());
        $old = $item->image_path;

        $this->post("/api/items/{$item->id}", $this->payload($item->shop, [
            '_method' => 'PUT',
            'image' => UploadedFile::fake()->image('new.png'),
        ]))->assertOk();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($item->fresh()->image_path);
        $this->assertNotSame($old, $item->fresh()->image_path);
    }

    public function test_update_without_image_keeps_existing(): void
    {
        $user = $this->actingAsUser();
        $item = $this->itemWithImage(Shop::factory()->for($user)->create());
        $old = $item->image_path;

        $this->putJson("/api/items/{$item->id}", $this->payload($item->shop, ['name' => 'Changed']))->assertOk();

        $this->assertSame($old, $item->fresh()->image_path);
        Storage::disk('public')->assertExists($old);
    }

    public function test_deleting_item_removes_file(): void
    {
        $user = $this->actingAsUser();
        $item = $this->itemWithImage(Shop::factory()->for($user)->create());

        $this->deleteJson("/api/items/{$item->id}")->assertOk();

        $this->assertModelMissing($item);
        Storage::disk('public')->assertMissing($item->image_path);
    }

    public function test_deleting_shop_removes_item_files(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();
        $item = $this->itemWithImage($shop);

        $this->deleteJson("/api/shops/{$shop->id}")->assertOk();

        $this->assertModelMissing($item);
        Storage::disk('public')->assertMissing($item->image_path);
    }

    public function test_other_user_cannot_view_update_or_delete(): void
    {
        $this->actingAsUser();
        $shop = Shop::factory()->for(User::factory()->create())->create();
        $item = Item::factory()->for($shop)->create();

        $this->getJson("/api/my/items/{$item->id}")->assertForbidden();
        $this->putJson("/api/items/{$item->id}", $this->payload($shop))->assertForbidden();
        $this->deleteJson("/api/items/{$item->id}")->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_my_items_filters_by_status_and_is_paginated(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create();
        Item::factory()->for($shop)->count(3)->create();
        Item::factory()->for($shop)->count(2)->create(['status' => 'sold']);
        Item::factory()->for(Shop::factory()->for(User::factory()->create()))->create(['status' => 'sold']);

        $this->getJson('/api/my/items?status=sold')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/my/items?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }
}
