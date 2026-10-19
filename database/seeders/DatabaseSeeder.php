<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            ['title' => 'Hades', 'slug' => 'hades', 'steam_app_id' => 1145360, 'description' => 'Roguelike dungeon crawler tentang Zagreus yang berusaha keluar dari dunia bawah.', 'genre' => 'Action Roguelike', 'release_date' => '2020-09-17', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1145360/capsule_616x353.jpg?t=1758127023', 'popularity' => 96],
            ['title' => 'Stardew Valley', 'slug' => 'stardew-valley', 'steam_app_id' => 413150, 'description' => 'Bangun kehidupan baru di desa, bertani, berteman, dan menjelajahi tambang.', 'genre' => 'Simulation', 'release_date' => '2016-02-26', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/413150/capsule_616x353.jpg', 'popularity' => 99],
            ['title' => 'Cyberpunk 2077', 'slug' => 'cyberpunk-2077', 'steam_app_id' => 1091500, 'description' => 'RPG aksi dunia terbuka berlatar Night City.', 'genre' => 'RPG', 'release_date' => '2020-12-10', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1091500/capsule_616x353.jpg', 'popularity' => 95],
            ['title' => 'Control Ultimate Edition', 'slug' => 'control-ultimate-edition', 'steam_app_id' => 870780, 'description' => 'Petualangan aksi supernatural di kantor pusat Federal Bureau of Control.', 'genre' => 'Action', 'release_date' => '2020-08-27', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/870780/capsule_616x353.jpg', 'popularity' => 84],
            ['title' => 'The Witcher 3: Wild Hunt', 'slug' => 'the-witcher-3-wild-hunt', 'steam_app_id' => 292030, 'description' => 'RPG fantasi dunia terbuka mengikuti perjalanan Geralt of Rivia.', 'genre' => 'RPG', 'release_date' => '2015-05-18', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/292030/capsule_616x353.jpg', 'popularity' => 98],
            ['title' => 'Celeste', 'slug' => 'celeste', 'steam_app_id' => 504230, 'description' => 'Platformer presisi tentang perjalanan Madeline mendaki gunung Celeste.', 'genre' => 'Platformer', 'release_date' => '2018-01-25', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/504230/capsule_616x353.jpg', 'popularity' => 91],
            ['title' => 'Dead Cells', 'slug' => 'dead-cells', 'steam_app_id' => 588650, 'description' => 'Roguelite aksi dengan pertarungan cepat dan level yang berubah.', 'genre' => 'Action Roguelike', 'release_date' => '2018-08-07', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/588650/capsule_616x353.jpg', 'popularity' => 89],
            ['title' => 'Sifu', 'slug' => 'sifu', 'steam_app_id' => 2138710, 'description' => 'Game aksi bela diri dengan mekanik penuaan setiap kali karakter tumbang.', 'genre' => 'Action', 'release_date' => '2022-02-08', 'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/2138710/capsule_616x353.jpg', 'popularity' => 82],
            ['title' => 'Warhammer 40,000: Space Marine 2','slug' => 'warhammer-40000-space-marine-2','steam_app_id' => 2183900,'description' => 'Game aksi Warhammer 40,000.','genre' => 'Action','release_date' => '2024-09-09','cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQFj-zhXvbyOYp1jZh_METOP_sOmWWN53sM52m5BjdlrtSl9rbX1FJhr8_Y&s=10','popularity' => 0,],
            ['title' => 'Hollow Knight: Silksong','slug' => 'hollow-knight-silksong','steam_app_id' => 1030300,'description' => 'Sekuel Hollow Knight, platformer aksi dengan dunia yang luas dan penuh rahasia.','genre' => 'Action Platformer','release_date' => '2024-10-12','cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQQm9Z8XonoaF_CVq8qPZcHTSheLN6D50ZsV-8uW0ZfKA&s=10','popularity' => 0,],
        ];

        foreach ($games as $data) {
            Game::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
