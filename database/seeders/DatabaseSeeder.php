<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            ['title' => 'Hades', 'slug' => 'hades', 'description' => 'Roguelike dungeon crawler tentang Zagreus yang berusaha keluar dari dunia bawah.', 'genre' => 'Action Roguelike', 'release_date' => '2020-09-17', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1145360/capsule_616x353.jpg?t=1758127023', 'popularity' => 96],
            ['title' => 'Stardew Valley', 'slug' => 'stardew-valley', 'description' => 'Bangun kehidupan baru di desa, bertani, berteman, dan menjelajahi tambang.', 'genre' => 'Simulation', 'release_date' => '2016-02-26', 'cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR1dsOGnbVSFNaBJIGKLwi7l-0RmQ7nf3no5eCRD1H4mA&s=10', 'popularity' => 99],
            ['title' => 'Cyberpunk 2077', 'slug' => 'cyberpunk-2077', 'description' => 'RPG aksi dunia terbuka berlatar Night City.', 'genre' => 'RPG', 'release_date' => '2020-12-10', 'cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQSX208ypxNBwySc5-izcdO94v20IGetf5F0kpbEvZBBA&s=10', 'popularity' => 95],
            ['title' => 'Control Ultimate Edition', 'slug' => 'control-ultimate-edition', 'description' => 'Petualangan aksi supernatural di kantor pusat Federal Bureau of Control.', 'genre' => 'Action', 'release_date' => '2020-08-27', 'cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTfdunL65tHXNspwT_DT2yBAL_SqGJBALZgO76BFQHdXg&s=10', 'popularity' => 84],
            ['title' => 'The Witcher 3: Wild Hunt', 'slug' => 'the-witcher-3-wild-hunt', 'description' => 'RPG fantasi dunia terbuka mengikuti perjalanan Geralt of Rivia.', 'genre' => 'RPG', 'release_date' => '2015-05-18', 'cover_image' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1wyy.png', 'popularity' => 98],
            ['title' => 'Celeste', 'slug' => 'celeste', 'description' => 'Platformer presisi tentang perjalanan Madeline mendaki gunung Celeste.', 'genre' => 'Platformer', 'release_date' => '2018-01-25', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/504230/capsule_616x353.jpg?t=1714089525', 'popularity' => 91],
            ['title' => 'Dead Cells', 'slug' => 'dead-cells', 'description' => 'Roguelite aksi dengan pertarungan cepat dan level yang berubah.', 'genre' => 'Action Roguelike', 'release_date' => '2018-08-07', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/588650/header.jpg?t=1779086887', 'popularity' => 89],
            ['title' => 'Sifu', 'slug' => 'sifu', 'description' => 'Game aksi bela diri dengan mekanik penuaan setiap kali karakter tumbang.', 'genre' => 'Action', 'release_date' => '2022-02-08', 'cover_image' => 'https://shared.fastly.steamstatic.com/store_item_assets/steam/apps/2138710/header.jpg?t=1782742326', 'popularity' => 82],
        ];

        foreach ($games as $index => $data) {
            $game = Game::updateOrCreate(['slug' => $data['slug']], $data);
            $offers = [
                ['platform' => 'Steam', 'original_price' => [139000, 149000, 799000, 499000, 389000, 108000, 125000, 299000][$index], 'discount_percentage' => [35, 20, 50, 75, 80, 40, 50, 30][$index]],
                ['platform' => 'Epic Games', 'original_price' => [139000, 149000, 799000, 499000, 389000, 108000, 125000, 299000][$index], 'discount_percentage' => [25, 15, 45, 70, 75, 30, 40, 25][$index]],
            ];

            foreach ($offers as $offer) {
                $finalPrice = (int) round($offer['original_price'] * (100 - $offer['discount_percentage']) / 100);
                $storeUrl = $offer['platform'] === 'Steam'
                    ? 'https://store.steampowered.com/search/?term='.urlencode($data['title'])
                    : 'https://store.epicgames.com/en-US/browse?q='.urlencode($data['title']);

                $gameOffer = $game->offers()->updateOrCreate(
                    ['platform' => $offer['platform']],
                    [...$offer, 'final_price' => $finalPrice, 'store_url' => $storeUrl],
                );
                $historySamples = [
                    ['recorded_at' => '2026-10-01 12:00:00', 'discount_percentage' => max(0, $offer['discount_percentage'] - 10)],
                    ['recorded_at' => '2026-10-04 12:00:00', 'discount_percentage' => max(0, $offer['discount_percentage'] - 5)],
                    ['recorded_at' => '2026-10-08 12:00:00', 'discount_percentage' => $offer['discount_percentage']],
                ];

                foreach ($historySamples as $snapshot) {
                    $snapshotFinalPrice = (int) round(
                        $offer['original_price'] * (100 - $snapshot['discount_percentage']) / 100,
                    );

                    $gameOffer->priceHistories()->updateOrCreate(
                        ['recorded_at' => $snapshot['recorded_at']],
                        [
                            'original_price' => $offer['original_price'],
                            'discount_percentage' => $snapshot['discount_percentage'],
                            'final_price' => $snapshotFinalPrice,
                            'is_simulated' => true,
                        ],
                    );
                }
            }
            $requirements = [
                    'minimum' => [
                        'operating_system' => 'Windows 10 64-bit',
                        'processor' => 'Intel Core i5-2500K / AMD FX-6300',
                        'memory' => '8 GB RAM',
                        'graphics' => 'NVIDIA GeForce GTX 770 2GB / AMD Radeon R9 280 3GB',
                        'network' => 'Koneksi Internet Broadband',
                        'storage' => '150 GB ruang tersedia',
                        'sound_card' => 'DirectX Compatible',
                    ],
                    'recommended' => [
                        'operating_system' => 'Windows 10 64-bit',
                        'processor' => 'Intel Core i7-4770K / AMD Ryzen 5 1500X',
                        'memory' => '12 GB RAM',
                        'graphics' => 'NVIDIA GeForce GTX 1060 6GB / AMD Radeon RX 480 4GB',
                        'network' => 'Koneksi Internet Broadband',
                        'storage' => '150 GB ruang tersedia',
                        'sound_card' => 'DirectX Compatible',
                    ],
                ];

                foreach ($requirements as $type => $specification) {
                    $game->systemRequirements()->updateOrCreate(
                        ['requirement_type' => $type],
                        $specification,
                    );
                }

                        }
    }
}
