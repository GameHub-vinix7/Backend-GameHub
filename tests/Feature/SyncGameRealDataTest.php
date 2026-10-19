<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncGameRealDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_create_fake_offers_or_history(): void
    {
        $this->seed();

        $this->assertDatabaseCount('games', 10);
        $this->assertDatabaseCount('game_offers', 0);
        $this->assertDatabaseCount('game_offer_histories', 0);
        $this->assertDatabaseCount('game_system_requirements', 0);
    }

    public function test_sync_command_requires_one_game_and_does_not_make_bulk_requests(): void
    {
        Http::fake();

        $this->artisan('games:sync-real-data')->assertExitCode(2);

        Http::assertSentCount(0);
    }

    public function test_legacy_seeded_offer_is_not_exposed_as_a_real_price(): void
    {
        $this->seed();
        $game = \App\Models\Game::where('slug', 'hades')->firstOrFail();
        $game->offers()->create([
            'platform' => 'Steam',
            'original_price' => 139000,
            'discount_percentage' => 35,
            'final_price' => 90350,
            'store_url' => 'https://store.steampowered.com/search/?term=Hades',
        ]);

        $this->getJson('/api/games/hades')->assertNotFound();
    }

    public function test_sync_persists_real_steam_metadata_price_and_exposes_them_to_api(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '1145360' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'steam_appid' => 1145360,
                        'name' => 'Hades',
                        'short_description' => 'Defy the god of the dead.',
                        'header_image' => 'https://cdn.example.test/hades.jpg',
                        'release_date' => ['date' => '17 Sep, 2020'],
                        'genres' => [['description' => 'Action Roguelike']],
                        'pc_requirements' => [
                            'minimum' => '<ul><li>OS: Windows 10 64-bit</li><li>Processor: Intel Core i5</li><li>Memory: 8 GB RAM</li><li>Graphics: GTX 770</li><li>Storage: 15 GB available space</li></ul>',
                            'recommended' => '<ul><li>OS: Windows 11 64-bit</li><li>Processor: Intel Core i7</li><li>Memory: 16 GB RAM</li><li>Graphics: RTX 2060</li><li>Storage: 15 GB available space</li></ul>',
                        ],
                        'price_overview' => [
                            'currency' => 'IDR',
                            'initial' => 13900000,
                            'final' => 8900000,
                            'discount_percent' => 36,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('games:sync-real-data', ['--game' => 'hades'])->assertSuccessful();

        $this->assertDatabaseHas('games', [
            'slug' => 'hades',
            'steam_app_id' => 1145360,
            'description' => 'Defy the god of the dead.',
        ]);
        $this->assertSame('2020-09-17', \App\Models\Game::where('slug', 'hades')->firstOrFail()->release_date->toDateString());
        $this->assertDatabaseHas('game_system_requirements', [
            'requirement_type' => 'minimum',
            'operating_system' => 'Windows 10 64-bit',
            'processor' => 'Intel Core i5',
            'memory' => '8 GB RAM',
            'graphics' => 'GTX 770',
        ]);
        $this->assertDatabaseHas('game_offers', [
            'platform' => 'Steam',
            'currency' => 'IDR',
            'source' => 'steam',
            'final_price' => 89000,
        ]);
        $this->assertDatabaseHas('game_offer_histories', [
            'is_simulated' => 0,
            'final_price' => 89000,
        ]);
        $this->getJson('/api/games/hades')
            ->assertOk()
            ->assertJsonPath('data.offers.0.platform', 'Steam')
            ->assertJsonPath('data.offers.0.currency', 'IDR')
            ->assertJsonPath('data.offers.0.final_price', 89000)
            ->assertJsonPath('data.offers.0.source', 'steam');
        $this->getJson('/api/games?platform=Steam')
            ->assertOk()
            ->assertJsonPath('data.0.offers.0.currency', 'IDR');
        $this->getJson('/api/games/platforms')->assertExactJson(['Steam']);
        $this->getJson('/api/games/hades/price-history?platform=Steam')
            ->assertOk()
            ->assertJsonPath('data_is_simulated', false);
    }

    public function test_cheapshark_fallback_stores_usd_deal_and_uses_its_redirect_link(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([], 503),
            'www.cheapshark.com/api/1.0/deals*' => Http::response([
                [
                    'dealID' => 'deal%2Fid',
                    'steamAppID' => '1145360',
                    'salePrice' => '3.99',
                    'normalPrice' => '19.99',
                    'savings' => '80.040020',
                    'title' => 'Hades',
                    'thumb' => 'https://cdn.example.test/hades.jpg',
                ],
            ]),
        ]);

        $this->artisan('games:sync-real-data', ['--game' => 'hades'])->assertSuccessful();

        $this->assertDatabaseHas('game_offers', [
            'platform' => 'CheapShark',
            'currency' => 'USD',
            'source' => 'cheapshark',
            'final_price' => 3.99,
            'original_price' => 19.99,
            'store_url' => 'https://www.cheapshark.com/redirect?dealID=deal%2Fid',
        ]);
        $this->assertDatabaseHas('games', [
            'slug' => 'hades',
            'title' => 'Hades',
            'cover_image' => 'https://cdn.example.test/hades.jpg',
        ]);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'cheapshark.com/api/1.0/deals')
                && str_starts_with(
                    $request->header('User-Agent')[0] ?? '',
                    'GameHubPriceTracker/1.0 (https://github.com/davidshdy17/GameHub',
                );
        });
        $this->getJson('/api/games/hades')
            ->assertOk()
            ->assertJsonPath('data.offers.0.platform', 'CheapShark')
            ->assertJsonPath('data.offers.0.currency', 'USD');
    }
}
