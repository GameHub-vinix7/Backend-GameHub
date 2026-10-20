<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncGogGameDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(string $slug = 'the-witcher-2', string $title = 'The Witcher 2: Assassins of Kings Enhanced Edition'): Game
    {
        return Game::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => 'Game description.',
            'genre' => 'RPG',
            'release_date' => '2011-05-17',
            'cover_image' => 'https://cdn.example.test/witcher2.jpg',
            'popularity' => 10,
        ]);
    }

    private function gogProduct(string $title, string $slug, string $currency = 'IDR', string $base = '34900', string $final = '13900'): array
    {
        return [
            'id' => '1207658930',
            'title' => $title,
            'slug' => $slug,
            'price' => [
                'baseMoney' => ['amount' => $base, 'currency' => $currency],
                'finalMoney' => ['amount' => $final, 'currency' => $currency, 'discount' => '60'],
            ],
        ];
    }

    private function fakeCatalog(array $products): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'catalog.gog.com/v1/catalog*' => Http::response([
                'productCount' => count($products),
                'products' => $products,
            ]),
            'api.gog.com/v2/games/*' => Http::response([
                '_embedded' => [
                    'supportedOperatingSystems' => [
                        [
                            'operatingSystem' => ['name' => 'windows'],
                            'systemRequirements' => [
                                ['type' => 'minimum', 'requirements' => [
                                    ['id' => 'system', 'description' => 'Windows 7'],
                                    ['id' => 'processor', 'description' => 'Dual Core 2.0 GHz'],
                                    ['id' => 'memory', 'description' => '2 GB RAM'],
                                    ['id' => 'graphics', 'description' => '512 MB VRAM'],
                                    ['id' => 'storage', 'description' => '25 GB available space'],
                                    ['id' => 'other', 'description' => 'Requires DirectX'],
                                ]],
                                ['type' => 'recommended', 'requirements' => [
                                    ['id' => 'system', 'description' => 'Windows 10'],
                                    ['id' => 'processor', 'description' => 'Quad Core 3.0 GHz'],
                                    ['id' => 'memory', 'description' => '4 GB RAM'],
                                    ['id' => 'graphics', 'description' => '1 GB VRAM'],
                                    ['id' => 'storage', 'description' => '25 GB available space'],
                                ]],
                            ],
                        ],
                        [
                            'operatingSystem' => ['name' => 'linux'],
                            'systemRequirements' => [
                                ['type' => 'minimum', 'requirements' => [
                                    ['id' => 'system', 'description' => 'Ubuntu 22.04'],
                                    ['id' => 'processor', 'description' => 'Processor: Dual Core 2.5 GHz'],
                                    ['id' => 'memory', 'description' => '4 GB RAM'],
                                    ['id' => 'graphics', 'description' => '1 GB VRAM'],
                                    ['id' => 'storage', 'description' => '30 GB available space'],
                                    ['id' => 'other', 'description' => 'Requires libc6:i386'],
                                ]],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    public function test_sync_persists_exact_gog_match_and_exposes_it_through_the_api(): void
    {
        $this->makeGame();
        $this->fakeCatalog([
            $this->gogProduct('The Witcher', 'the_witcher'),
            $this->gogProduct('The Witcher 2: Assassins of Kings Enhanced Edition', 'the_witcher_2'),
        ]);

        $this->artisan('games:sync-gog', ['--game' => 'the-witcher-2'])->assertSuccessful();

        $this->assertDatabaseHas('game_offers', [
            'platform' => 'GOG',
            'source' => 'gog',
            'currency' => 'IDR',
            'original_price' => 34900,
            'final_price' => 13900,
            'discount_percentage' => 60,
            'store_url' => 'https://www.gog.com/en/game/the_witcher_2',
        ]);
        $this->assertDatabaseHas('game_system_requirements', [
            'requirement_type' => 'minimum',
            'platform' => 'GOG',
            'operating_system' => 'Windows 7; Linux: Ubuntu 22.04',
            'processor' => 'Windows: Dual Core 2.0 GHz; Linux: Dual Core 2.5 GHz',
            'memory' => 'Windows: 2 GB RAM; Linux: 4 GB RAM',
            'graphics' => 'Windows: 512 MB VRAM; Linux: 1 GB VRAM',
            'storage' => 'Windows: 25 GB available space; Linux: 30 GB available space',
            'other' => 'Windows: Requires DirectX; Linux: Requires libc6:i386',
        ]);
        $this->assertDatabaseHas('game_system_requirements', [
            'requirement_type' => 'recommended',
            'platform' => 'GOG',
            'operating_system' => 'Windows 10',
            'processor' => 'Windows: Quad Core 3.0 GHz',
        ]);
        $this->assertDatabaseCount('game_offer_histories', 1);
        $indexes = collect(Schema::getIndexes('game_system_requirements'));
        $this->assertTrue($indexes->contains(fn (array $index) => $index['columns'] === ['game_id']));
        $this->assertTrue($indexes->contains(fn (array $index) => $index['columns'] === ['game_id', 'platform', 'requirement_type'] && $index['unique']));
        $this->getJson('/api/games?platform=GOG')
            ->assertOk()
            ->assertJsonPath('data.0.offers.0.platform', 'GOG')
            ->assertJsonPath('data.0.offers.0.currency', 'IDR');
        $this->getJson('/api/games/the-witcher-2')
            ->assertOk()
            ->assertJsonPath('data.system_requirements.0.platform', 'GOG')
            ->assertJsonPath('data.system_requirements.0.operating_system', 'Windows 7; Linux: Ubuntu 22.04');
        $this->getJson('/api/games/platforms')->assertExactJson(['GOG']);
        $this->getJson('/api/games/the-witcher-2/price-history?platform=GOG')
            ->assertOk()
            ->assertJsonPath('series.0.platform', 'GOG');

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://catalog.gog.com/v1/catalog')
            && str_contains($request->url(), 'query=like%3AThe%20Witcher'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.gog.com/v2/games/1207658930');
    }

    public function test_sync_requires_one_catalog_game_and_does_not_make_unbounded_requests(): void
    {
        Http::fake();

        $this->artisan('games:sync-gog')->assertExitCode(2);

        Http::assertSentCount(0);
    }

    public function test_non_idr_or_unmatched_gog_products_are_not_saved(): void
    {
        $this->makeGame();
        $this->fakeCatalog([
            $this->gogProduct('The Witcher 2: Assassins of Kings Enhanced Edition', 'the_witcher_2', 'USD', '20', '8'),
        ]);

        $this->artisan('games:sync-gog', ['--game' => 'the-witcher-2'])->assertFailed();

        $this->assertDatabaseCount('game_offers', 0);
        $this->assertDatabaseCount('game_offer_histories', 0);
    }

    public function test_repeat_sync_does_not_duplicate_offer_or_unchanged_price_history(): void
    {
        $this->makeGame();
        $this->fakeCatalog([$this->gogProduct('The Witcher 2: Assassins of Kings Enhanced Edition', 'the_witcher_2')]);

        $this->artisan('games:sync-gog', ['--game' => 'the-witcher-2'])->assertSuccessful();
        $this->artisan('games:sync-gog', ['--game' => 'the-witcher-2'])->assertSuccessful();

        $this->assertDatabaseCount('game_offers', 1);
        $this->assertDatabaseCount('game_offer_histories', 1);
    }

    public function test_gog_requirements_do_not_overwrite_steam_requirements(): void
    {
        $game = $this->makeGame();
        $game->systemRequirements()->create([
            'platform' => 'Steam',
            'requirement_type' => 'minimum',
            'processor' => 'Steam CPU data',
        ]);
        $this->fakeCatalog([$this->gogProduct('The Witcher 2: Assassins of Kings Enhanced Edition', 'the_witcher_2')]);

        $this->artisan('games:sync-gog', ['--game' => 'the-witcher-2'])->assertSuccessful();

        $this->assertDatabaseHas('game_system_requirements', [
            'game_id' => $game->id,
            'platform' => 'Steam',
            'requirement_type' => 'minimum',
            'processor' => 'Steam CPU data',
        ]);
        $this->assertDatabaseHas('game_system_requirements', [
            'game_id' => $game->id,
            'platform' => 'GOG',
            'requirement_type' => 'minimum',
        ]);
    }

    public function test_gog_details_failure_does_not_block_price_sync(): void
    {
        $this->makeGame();
        Http::preventStrayRequests();
        Http::fake([
            'catalog.gog.com/v1/catalog*' => Http::response([
                'productCount' => 1,
                'products' => [$this->gogProduct('The Witcher 2: Assassins of Kings Enhanced Edition', 'the_witcher_2')],
            ]),
            'api.gog.com/v2/games/*' => Http::response([], 503),
        ]);

        $this->artisan('games:sync-gog', ['--game' => 'the-witcher-2'])->assertSuccessful();

        $this->assertDatabaseCount('game_offers', 1);
        $this->assertDatabaseCount('game_system_requirements', 0);
    }
}
