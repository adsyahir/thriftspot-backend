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
    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'username' => 'admin', 'password' => Hash::make('password')]
        );
        $admin->assignRole('admin');

        $seller = User::firstOrCreate(
            ['email' => 'seller@example.com'],
            ['name' => 'Demo Seller', 'username' => 'seller', 'password' => Hash::make('password')]
        );
        $seller->assignRole('user');

        if (User::count() < 12) {
            User::factory()->count(10)->create()->each(fn (User $user) => $user->assignRole('user'));
        }

        foreach ($this->shops() as $data) {
            $items = $data['items'];
            unset($data['items']);

            $owner = $data['owner'] === 'admin' ? $admin : $seller;
            unset($data['owner']);

            $shop = Shop::firstOrCreate(['name' => $data['name']], $data + ['user_id' => $owner->id]);

            foreach ($items as [$name, $description, $category, $condition, $price, $status]) {
                $item = Item::firstOrCreate(
                    ['shop_id' => $shop->id, 'name' => $name],
                    compact('description', 'category', 'condition', 'price', 'status')
                );

                if ($item->wasRecentlyCreated && $status !== 'hidden') {
                    $this->seedViews($item);
                }
            }
        }
    }

    private function seedViews(Item $item): void
    {
        $popularity = random_int(1, 8);

        foreach (range(0, 29) as $daysAgo) {
            ItemViewDay::create([
                'item_id' => $item->id,
                'viewed_on' => today()->subDays($daysAgo)->toDateString(),
                'count' => random_int(0, $popularity),
            ]);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>|false
     */
    private function hours(string $open, string $close, ?string $closedDay = 'sunday'): array
    {
        $hours = [];
        foreach (self::WEEKDAYS as $day) {
            $hours[$day] = $day === $closedDay ? false : [$open, $close];
        }

        return $hours;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shops(): array
    {
        return [
            [
                'owner' => 'admin',
                'name' => 'Kedai Barang Lama Bangsar',
                'description' => 'Vintage clothing, retro electronics and homeware, curated weekly in the heart of Bangsar.',
                'state' => 'kuala_lumpur',
                'location_type' => 'google_maps',
                'address' => 'Jalan Telawi 3, Bangsar Baru, 59100 Kuala Lumpur',
                'phone_number' => '+60 3-2284 1122',
                'email' => 'hello@barangbangsar.example',
                'social_links' => ['instagram' => 'https://instagram.com/barangbangsar', 'whatsapp' => '+60322841122'],
                'operating_hours' => $this->hours('10:00', '19:00'),
                'items' => [
                    ['Vintage Levi\'s 501 Jeans', 'Mid-wash straight leg, size 32. Soft from years of wear, no tears.', 'fashion', 'good', 85.00, 'active'],
                    ['Sony Walkman WM-EX150', 'Working cassette Walkman with belt clip. New batteries tested before listing.', 'electronics', 'fair', 120.00, 'active'],
                    ['Teak Side Table', 'Mid-century teak side table with tapered legs. Minor ring marks on top.', 'home', 'good', 150.00, 'active'],
                    ['Batik Silk Scarf', 'Hand-drawn batik on silk, deep indigo and gold.', 'fashion', 'like_new', 55.00, 'active'],
                    ['Canon AE-1 Film Camera', 'Body with 50mm f/1.8 lens. Light meter works, seals replaced.', 'electronics', 'good', 480.00, 'sold'],
                    ['Rattan Peacock Chair', 'Large rattan chair, sturdy frame, one small repair on the back.', 'home', 'fair', 220.00, 'active'],
                ],
            ],
            [
                'owner' => 'admin',
                'name' => 'Penang Thrift Corner',
                'description' => 'Family-run shop in George Town with secondhand clothes, books and sports gear.',
                'state' => 'penang',
                'location_type' => 'google_maps',
                'address' => 'Lebuh Carnarvon, George Town, 10100 Penang',
                'phone_number' => '+60 4-261 3344',
                'email' => 'penangthrift@example.com',
                'social_links' => ['facebook' => 'https://facebook.com/penangthriftcorner'],
                'operating_hours' => $this->hours('09:30', '18:00', 'monday'),
                'items' => [
                    ['Adidas Originals Track Jacket', 'Navy with white stripes, size M. Zip and cuffs in great shape.', 'fashion', 'good', 60.00, 'active'],
                    ['Yonex Badminton Racket Duo', 'Two graphite rackets with fresh grips and a carry bag.', 'sports', 'good', 95.00, 'active'],
                    ['Road Bike, 54cm Frame', 'Aluminium frame, 2x8 gears, new tyres. Test rides welcome in store.', 'sports', 'fair', 380.00, 'active'],
                    ['Cast Iron Wok', 'Seasoned 32cm cast iron wok with wooden handle.', 'home', 'good', 70.00, 'active'],
                    ['Yoga Mat and Blocks Set', 'Barely used 6mm mat with two foam blocks.', 'sports', 'like_new', 35.00, 'sold'],
                ],
            ],
            [
                'owner' => 'seller',
                'name' => 'Retro Tech Johor',
                'description' => 'Tested secondhand phones, consoles and audio gear in Johor Bahru.',
                'state' => 'johor',
                'location_type' => 'coordinates',
                'latitude' => 1.4927,
                'longitude' => 103.7414,
                'phone_number' => '+60 7-223 5566',
                'email' => 'retrotechjb@example.com',
                'social_links' => ['tiktok' => 'https://tiktok.com/@retrotechjb', 'whatsapp' => '+6072235566'],
                'operating_hours' => $this->hours('11:00', '21:00', null),
                'items' => [
                    ['Nintendo Switch (V1) with Dock', 'Console, dock and both Joy-Cons. Battery health checked.', 'electronics', 'good', 650.00, 'active'],
                    ['iPhone 11 64GB Black', 'Unlocked, 86% battery health, small scratch on the frame.', 'electronics', 'good', 780.00, 'active'],
                    ['JBL Flip 5 Speaker', 'Waterproof Bluetooth speaker, includes charging cable.', 'electronics', 'like_new', 260.00, 'active'],
                    ['Mechanical Keyboard (Brown Switches)', '75% layout, hot-swappable, PBT keycaps.', 'electronics', 'like_new', 190.00, 'active'],
                    ['Old Stock CRT Television', 'Working 21 inch CRT. Pickup only.', 'electronics', 'fair', 90.00, 'hidden'],
                ],
            ],
            [
                'owner' => 'seller',
                'name' => 'Selangor Home Revival',
                'description' => 'Pre-loved furniture and kitchenware from Petaling Jaya households.',
                'state' => 'selangor',
                'location_type' => 'google_maps',
                'address' => 'Jalan SS 2/24, Petaling Jaya, 47300 Selangor',
                'phone_number' => '+60 3-7877 9900',
                'email' => 'homerevival@example.com',
                'social_links' => ['instagram' => 'https://instagram.com/homerevival.my'],
                'operating_hours' => $this->hours('10:00', '18:00', 'tuesday'),
                'items' => [
                    ['IKEA Kallax Shelf (4x2)', 'White shelving unit, comes apart for transport.', 'home', 'good', 110.00, 'active'],
                    ['Philips Air Fryer', '4.1L air fryer, basket recoated, works perfectly.', 'home', 'good', 130.00, 'active'],
                    ['Ceramic Dinner Set (24 pieces)', 'Hand-painted floral set, complete, no chips.', 'home', 'like_new', 99.00, 'active'],
                    ['Standing Desk Frame', 'Electric height-adjustable frame, no tabletop.', 'home', 'good', 240.00, 'active'],
                ],
            ],
            [
                'owner' => 'seller',
                'name' => 'Sarawak Pre-Loved Fashion',
                'description' => 'Curated pre-loved clothing and accessories, based in Kuching.',
                'state' => 'sarawak',
                'location_type' => 'google_maps',
                'address' => 'Jalan Padungan, 93100 Kuching, Sarawak',
                'phone_number' => '+60 82-411 220',
                'email' => 'sarawakpreloved@example.com',
                'social_links' => ['instagram' => 'https://instagram.com/sarawakpreloved'],
                'operating_hours' => $this->hours('10:00', '22:00', null),
                'items' => [
                    ['Uniqlo Linen Shirt', 'Sage green, size L, softened by washing.', 'fashion', 'like_new', 28.00, 'active'],
                    ['Leather Messenger Bag', 'Full-grain leather, brass hardware, fits a 14 inch laptop.', 'fashion', 'good', 140.00, 'active'],
                    ['Songket Evening Clutch', 'Handwoven songket clutch with magnetic clasp.', 'fashion', 'new', 75.00, 'active'],
                    ['Nike Air Max 90, UK 9', 'White and grey, midsoles in good condition.', 'fashion', 'good', 190.00, 'sold'],
                ],
            ],
            [
                'owner' => 'seller',
                'name' => 'Melaka Heritage Finds',
                'description' => 'Antiques, collectibles and curios from around Melaka.',
                'state' => 'melaka',
                'location_type' => 'google_maps',
                'address' => 'Jalan Hang Jebat, 75200 Melaka',
                'phone_number' => '+60 6-282 7788',
                'email' => 'melakafinds@example.com',
                'social_links' => [],
                'operating_hours' => $this->hours('12:00', '20:00', 'wednesday'),
                'items' => [
                    ['Peranakan Porcelain Tea Set', 'Floral enamel set with six cups, one hairline on the lid.', 'home', 'fair', 320.00, 'active'],
                    ['Brass Table Lamp', 'Restored brass lamp with a new linen shade.', 'home', 'good', 85.00, 'active'],
                    ['Vintage Football Jersey (Malaysia, 1990s)', 'Collectible replica jersey, size L.', 'sports', 'good', 160.00, 'active'],
                ],
            ],
        ];
    }
}
