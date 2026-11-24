<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemViewDay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('admin');

        $users = User::factory()
            ->count(20)
            ->create()
            ->each(fn (User $user) => $user->assignRole('user'));

        $shops = Shop::factory()
            ->count(6)
            ->sequence(fn ($sequence) => ['user_id' => $sequence->index < 3 ? $admin->id : $users->random()->id])
            ->create();

        $items = Item::factory()
            ->count(40)
            ->sequence(fn () => ['shop_id' => $shops->random()->id])
            ->create();

        foreach ($items as $item) {
            foreach (range(0, 29) as $daysAgo) {
                ItemViewDay::create([
                    'item_id' => $item->id,
                    'viewed_on' => today()->subDays($daysAgo)->toDateString(),
                    'count' => random_int(0, 12),
                ]);
            }
        }
    }
}
