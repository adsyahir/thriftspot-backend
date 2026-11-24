<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Vintage Corner',
            'description' => 'Pre-loved finds.',
            'state' => 'selangor',
            'location_type' => 'google_maps',
            'address' => 'Jalan Ampang, Kuala Lumpur',
            'place_id' => 'PLACE1',
            'phone_number' => '+60 12-345 6789',
            'email' => 'shop@example.com',
            'social_links' => ['instagram' => 'https://instagram.com/vintage', 'whatsapp' => '+60123456789'],
            'operating_hours' => [
                'monday' => ['10:00', '18:00'],
                'tuesday' => ['10:00', '18:00'],
                'wednesday' => ['10:00', '18:00'],
                'thursday' => ['10:00', '18:00'],
                'friday' => ['10:00', '18:00'],
                'saturday' => ['10:00', '18:00'],
                'sunday' => false,
            ],
        ], $overrides);
    }

    public function test_guest_cannot_create_shop(): void
    {
        $this->postJson('/api/shops', $this->payload())->assertUnauthorized();
    }

    public function test_user_creates_shop_with_false_and_hours(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/shops', $this->payload())->assertCreated();

        $this->assertFalse($response->json('data.operating_hours.sunday'));
        $this->assertSame(['10:00', '18:00'], $response->json('data.operating_hours.monday'));
        $this->assertDatabaseHas('shops', ['name' => 'Vintage Corner', 'user_id' => $user->id]);
    }

    public function test_coordinates_type_requires_lat_lng(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/shops', $this->payload(['location_type' => 'coordinates', 'address' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_google_maps_type_requires_address(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/shops', $this->payload(['address' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    public function test_rejects_javascript_social_link(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/shops', $this->payload(['social_links' => ['instagram' => 'javascript:alert(1)']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['social_links.instagram']);
    }

    public function test_accepts_overnight_hours(): void
    {
        $this->actingAsUser();
        $hours = $this->payload()['operating_hours'];
        $hours['friday'] = ['18:00', '02:00'];

        $this->postJson('/api/shops', $this->payload(['operating_hours' => $hours]))
            ->assertCreated()
            ->assertJsonPath('data.operating_hours.friday', ['18:00', '02:00']);
    }

    public function test_rejects_half_filled_or_missing_day(): void
    {
        $this->actingAsUser();
        $hours = $this->payload()['operating_hours'];

        $half = $hours;
        $half['monday'] = ['10:00', null];
        $this->postJson('/api/shops', $this->payload(['operating_hours' => $half]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operating_hours.monday']);

        $missing = $hours;
        unset($missing['tuesday']);
        $this->postJson('/api/shops', $this->payload(['operating_hours' => $missing]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operating_hours.tuesday']);
    }

    public function test_my_shops_lists_only_own(): void
    {
        $user = $this->actingAsUser();
        $mine = Shop::factory()->for($user)->create();
        Shop::factory()->for(User::factory()->create())->create();

        $this->getJson('/api/my/shops')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_other_user_cannot_update_or_delete(): void
    {
        $this->actingAsUser();
        $shop = Shop::factory()->for(User::factory()->create())->create();

        $this->putJson("/api/shops/{$shop->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/shops/{$shop->id}")->assertForbidden();
        $this->assertModelExists($shop);
    }

    public function test_update_to_coordinates_with_null_address_clears_old_values(): void
    {
        $user = $this->actingAsUser();
        $shop = Shop::factory()->for($user)->create(['address' => 'Old Street', 'place_id' => 'P1']);

        $this->putJson("/api/shops/{$shop->id}", $this->payload([
            'location_type' => 'coordinates',
            'address' => null,
            'place_id' => null,
            'latitude' => 3.1,
            'longitude' => 101.6,
        ]))->assertOk();

        $fresh = $shop->fresh();
        $this->assertNull($fresh->address);
        $this->assertNull($fresh->place_id);
        $this->assertSame(3.1, $fresh->latitude);
    }

    public function test_admin_can_delete_any_shop(): void
    {
        $this->actingAsUser(role: 'admin');
        $shop = Shop::factory()->for(User::factory()->create())->create();

        $this->deleteJson("/api/shops/{$shop->id}")->assertOk();
        $this->assertModelMissing($shop);
    }

    public function test_public_can_view_shop_without_user_id(): void
    {
        $shop = Shop::factory()->for(User::factory()->create())->create();

        $this->getJson("/api/shops/{$shop->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $shop->id)
            ->assertJsonMissingPath('data.user_id');
    }
}
