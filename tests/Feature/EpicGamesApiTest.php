<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpicGamesApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(string $slug, string $title): Game
    {
        return Game::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => "Deskripsi {$title} dari backend.",
            'genre' => 'Action',
            'release_date' => '2024-01-01',
            'cover_image' => "https://cdn.example.test/{$slug}.jpg",
            'popularity' => 50,
            'last_synced_at' => now(),
        ]);
    }

    public function test_epic_offers_from_the_api_are_served_with_game_identity_price_and_discount(): void
    {
        foreach ([
            ['hades', 'Hades', 139000, 35, 90350],
            ['celeste', 'Celeste', 99000, 50, 49500],
            ['sifu', 'Sifu', 219000, 20, 175200],
        ] as [$slug, $title, $original, $discount, $final]) {
            $game = $this->makeGame($slug, $title);
            $game->offers()->create([
                'platform' => 'Epic Games',
                'source' => 'epic',
                'currency' => 'IDR',
                'original_price' => $original,
                'discount_percentage' => $discount,
                'final_price' => $final,
                'store_url' => "https://store.epicgames.com/p/{$slug}",
            ]);
        }

        $response = $this->getJson('/api/games?platform=Epic%20Games&per_page=50')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $payload = $response->json('data');
        $this->assertSame(['Celeste', 'Hades', 'Sifu'], collect($payload)->pluck('title')->sort()->values()->all());

        foreach ($payload as $game) {
            $this->assertSame(1, count($game['offers']));
            $offer = $game['offers'][0];
            $this->assertSame('Epic Games', $offer['platform']);
            $this->assertSame('IDR', $offer['currency']);
            $this->assertSame(
                (int) round((1 - $offer['final_price'] / $offer['original_price']) * 100),
                (int) $offer['discount_percentage'],
            );
            $this->assertStringStartsWith('https://store.epicgames.com/p/', $offer['store_url']);
            $this->assertGreaterThan($offer['final_price'], $offer['original_price']);
        }
    }

    public function test_epic_detail_exposes_system_requirements_only_when_the_api_has_them(): void
    {
        $game = $this->makeGame('hades', 'Hades');
        $game->offers()->create([
            'platform' => 'Epic Games',
            'source' => 'epic',
            'currency' => 'IDR',
            'original_price' => 139000,
            'discount_percentage' => 35,
            'final_price' => 90350,
            'store_url' => 'https://store.epicgames.com/p/hades',
        ]);
        $game->systemRequirements()->create([
            'requirement_type' => 'minimum',
            'operating_system' => 'Windows 10',
            'processor' => 'Intel Core i5',
            'memory' => '8 GB RAM',
            'graphics' => 'GTX 770',
            'network' => null,
            'storage' => '15 GB',
            'sound_card' => null,
        ]);

        $this->getJson('/api/games/hades')
            ->assertOk()
            ->assertJsonPath('data.title', 'Hades')
            ->assertJsonPath('data.offers.0.platform', 'Epic Games')
            ->assertJsonPath('data.offers.0.final_price', 90350)
            ->assertJsonPath('data.system_requirements.0.type', 'minimum')
            ->assertJsonPath('data.system_requirements.0.operating_system', 'Windows 10')
            ->assertJsonPath('data.system_requirements.0.processor', 'Intel Core i5');
    }

    public function test_a_game_with_steam_and_epic_offers_returns_both_without_breaking_steam(): void
    {
        $game = $this->makeGame('cyberpunk-2077', 'Cyberpunk 2077');
        $game->offers()->createMany([
            [
                'platform' => 'Steam',
                'source' => 'steam',
                'currency' => 'IDR',
                'original_price' => 699000,
                'discount_percentage' => 50,
                'final_price' => 349500,
                'store_url' => 'https://store.steampowered.com/app/1091500/',
            ],
            [
                'platform' => 'Epic Games',
                'source' => 'epic',
                'currency' => 'IDR',
                'original_price' => 699000,
                'discount_percentage' => 40,
                'final_price' => 419400,
                'store_url' => 'https://store.epicgames.com/p/cyberpunk-2077',
            ],
        ]);

        $detail = $this->getJson('/api/games/cyberpunk-2077')->assertOk()->json('data');
        $platforms = collect($detail['offers'])->pluck('platform')->sort()->values()->all();
        $this->assertSame(['Epic Games', 'Steam'], $platforms);

        $steam = collect($detail['offers'])->firstWhere('platform', 'Steam');
        $epic = collect($detail['offers'])->firstWhere('platform', 'Epic Games');
        $this->assertSame(349500.0, (float) $steam['final_price']);
        $this->assertSame(419400.0, (float) $epic['final_price']);

        $this->getJson('/api/games?platform=Steam&per_page=50')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.offers.0.platform', 'Steam');
    }
}
