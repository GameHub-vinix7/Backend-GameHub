<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncEpicGameDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(string $slug = 'hades', string $title = 'Hades'): Game
    {
        return Game::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => 'Backend description.',
            'genre' => 'Action',
            'release_date' => '2020-09-17',
            'cover_image' => 'https://cdn.example.test/hades.jpg',
            'popularity' => 10,
        ]);
    }

    private function fakeSearch(array $offers): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.egdata.app/search/v2/search*' => Http::response([
                'total' => count($offers),
                'offers' => $offers,
                'page' => 1,
                'limit' => 10,
            ]),
        ]);
    }

    private function epicOffer(string $title, string $urlSlug, string $currency, int $original, int $final, int $discount = 0): array
    {
        return [
            'id' => 'epic-'.$urlSlug,
            'title' => $title,
            'urlSlug' => $urlSlug,
            'offerType' => 'BASE_GAME',
            'price' => ['price' => [
                'currencyCode' => $currency,
                'originalPrice' => $original,
                'discountPrice' => $final,
                'discount' => $discount,
            ]],
        ];
    }

    public function test_command_stores_the_exact_epic_match_with_idr_price_and_discount(): void
    {
        $this->makeGame();
        $this->fakeSearch([
            $this->epicOffer('Hades II', 'hades-ii', 'IDR', 24599900, 24599900),
            $this->epicOffer('Hades', 'hades', 'IDR', 13900000, 9035000, 35),
        ]);

        $this->artisan('games:sync-epic', ['--game' => 'hades'])->assertSuccessful();

        $this->assertDatabaseHas('game_offers', [
            'platform' => 'Epic Games',
            'source' => 'epic',
            'currency' => 'IDR',
            'original_price' => 139000,
            'final_price' => 90350,
            'discount_percentage' => 35,
            'store_url' => 'https://store.epicgames.com/en-US/p/hades',
        ]);
        $this->assertDatabaseCount('game_offers', 1);
        $this->assertDatabaseCount('game_offer_histories', 1);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.egdata.app/search/v2/search')
                && $request['title'] === 'Hades'
                && $request['page'] === 1;
        });
    }

    public function test_idr_offer_is_preferred_over_a_usd_duplicate_with_the_same_title(): void
    {
        $this->makeGame();
        $this->fakeSearch([
            $this->epicOffer('Hades', 'hades', 'USD', 1999, 1999),
            $this->epicOffer('Hades', 'hades', 'IDR', 20699900, 20699900),
        ]);

        $this->artisan('games:sync-epic', ['--game' => 'hades'])->assertSuccessful();

        $this->assertDatabaseHas('game_offers', [
            'platform' => 'Epic Games',
            'currency' => 'IDR',
            'final_price' => 206999,
        ]);
    }

    public function test_canonical_product_slug_matches_a_title_with_a_suffix(): void
    {
        $this->makeGame('the-witcher-3-wild-hunt', 'The Witcher 3: Wild Hunt');
        $offer = $this->epicOffer('The Witcher 3: Wild Hunt — Remastered', 'the-witcher-3-wild-hunt', 'IDR', 53900000, 53900000);
        $this->fakeSearch([$offer]);

        $this->artisan('games:sync-epic', ['--game' => 'the-witcher-3-wild-hunt'])->assertSuccessful();

        $this->assertDatabaseHas('game_offers', [
            'platform' => 'Epic Games',
            'final_price' => 539000,
            'store_url' => 'https://store.epicgames.com/en-US/p/the-witcher-3-wild-hunt',
        ]);
    }

    public function test_edition_offers_are_accepted_for_ultimate_editions(): void
    {
        $this->makeGame('control-ultimate-edition', 'CONTROL Ultimate Edition');
        $offer = $this->epicOffer('Control Ultimate Edition', 'control-ultimate-edition', 'IDR', 48599900, 48599900);
        $offer['offerType'] = 'EDITION';
        $this->fakeSearch([$offer]);

        $this->artisan('games:sync-epic', ['--game' => 'control-ultimate-edition'])->assertSuccessful();

        $this->assertDatabaseHas('game_offers', [
            'platform' => 'Epic Games',
            'final_price' => 485999,
        ]);
    }

    public function test_repeated_sync_updates_the_offer_without_duplicating_rows_or_history(): void
    {
        $this->makeGame();
        $this->fakeSearch([$this->epicOffer('Hades', 'hades', 'IDR', 13900000, 9035000, 35)]);

        $this->artisan('games:sync-epic', ['--game' => 'hades'])->assertSuccessful();
        $this->artisan('games:sync-epic', ['--game' => 'hades'])->assertSuccessful();

        $this->assertDatabaseCount('game_offers', 1);
        $this->assertDatabaseCount('game_offer_histories', 1);
    }

    public function test_non_idr_price_is_never_published_as_rupiah(): void
    {
        $this->makeGame();
        $this->fakeSearch([$this->epicOffer('Hades', 'hades', 'USD', 1999, 999, 50)]);

        $this->artisan('games:sync-epic', ['--game' => 'hades'])->assertFailed();

        $this->assertDatabaseCount('game_offers', 0);
    }

    public function test_unmatched_title_is_skipped_and_existing_offer_is_left_untouched(): void
    {
        $game = $this->makeGame();
        $game->offers()->create([
            'platform' => 'Epic Games',
            'source' => 'epic',
            'currency' => 'IDR',
            'original_price' => 100000,
            'discount_percentage' => 0,
            'final_price' => 100000,
            'store_url' => 'https://store.epicgames.com/en-US/p/hades',
        ]);
        $this->fakeSearch([$this->epicOffer('Hades II', 'hades-ii', 'IDR', 24599900, 24599900)]);

        $this->artisan('games:sync-epic', ['--game' => 'hades'])->assertFailed();

        $this->assertDatabaseCount('game_offers', 1);
        $this->assertDatabaseHas('game_offers', ['final_price' => 100000]);
    }
}
