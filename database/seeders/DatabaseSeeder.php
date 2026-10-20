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
                // tambah game gog
            [
            'title' => 'The Witcher 2: Assassins of Kings Enhanced Edition',
            'slug' => 'the-witcher-2',
            'description' => 'RPG aksi fantasi.',
            'genre' => 'RPG',
            'release_date' => '2011-05-17',
            'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/20920/capsule_616x353.jpg?t=1761657279',
            'popularity' => 0,
            ],
                        [
                'title' => 'OCTOPATH TRAVELER™',
                'slug' => 'octopath_traveler',
                'description' => 'Eight travelers. Eight adventures. Eight roles to play. Embark on an epic journey across the vast and wondrous world of Orsterra and discover the captivating stories of each of the eight travelers.',
                'genre' => 'Adventure',
                'release_date' => '2019-06-7',
                'cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQL_47E_HzO7Q9PKGaR6YhbCTlZVl3ZkuBA7m25WLsYEn1AiT3A_KUoi1A&s=10',
                'popularity' => 0,
            ],
                                    [
                'title' => 'Control Ultimate Edition',
                'slug' => 'control_ultimate_edition',
                'description' => 'Control Ultimate Edition contains the main game and all previously released Expansions ("The Foundation" and "AWE") in one great value package. 

                A corruptive presence has invaded the Federal Bureau of Control…Only you have the power to stop it. The world is now your weapon in an epic fight to annihilate an ominous enemy through deep and unpredictable environments. Containment has failed, humanity is at stake. Will you regain control?

                Winner of over 80 awards, Control is a visually stunning third-person action-adventure that will keep you on the edge of your seat. Blending open-ended environments with the signature world-building and storytelling of renowned developer, Remedy Entertainment, Control presents an expansive and intensely gratifying gameplay experience.',
                'genre' => 'Adventure',
                'release_date' => '2020-09-10',
                'cover_image' => 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/870780/4276f1547eeda445312ef44655cf1b66c917a5e8/capsule_616x353.jpg?t=1781607969',
                'popularity' => 0,
            ],
                                    [
                'title' => 'Warhammer 40,000: Space Marine',
                'slug' => 'warhammer_40000_space_marine',
                'description' => 'Eight travelers. Eight adventures. Eight roles to play. Embark on an epic journey across the vast and wondrous world of Orsterra and discover the captivating stories of each of the eight travelers.',
                'genre' => 'Adventure',
                'release_date' => '2011-09-9',
                'cover_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT-kWYeujPKhRgIwd0TY9TWYulF4cINTEYJ-tRNP2ohrA&s=10',
                'popularity' => 0,
            ],
        ];

        foreach ($games as $data) {
            Game::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
