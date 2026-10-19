<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameOffer;
use App\Services\CheapSharkService;
use App\Services\SteamStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Throwable;

class SyncGameRealDataCommand extends Command
{
    protected $signature = 'games:sync-real-data {--game= : Sync exactly one existing game by slug}';

    protected $description = 'Fetch one game price from Steam, fall back to CheapShark, and persist it before the API serves it';

    public function handle(SteamStoreService $steam, CheapSharkService $cheapShark): int
    {
        $slug = trim((string) $this->option('game'));

        if ($slug === '') {
            $this->error('Specify exactly one game: --game=<slug>. Bulk synchronization is intentionally disabled.');

            return self::INVALID;
        }

        $game = Game::query()->where('slug', $slug)->first();

        if (! $game) {
            $this->error("Game '{$slug}' was not found in MySQL. Seed its catalog identity first.");

            return self::FAILURE;
        }

        if (! $game->steam_app_id) {
            $this->error("Game '{$slug}' has no Steam App ID mapping; no external request was made.");

            return self::FAILURE;
        }

        $steamData = $steam->fetchGame((int) $game->steam_app_id);

        if ($steamData !== null) {
            $metadata = array_filter([
                'title' => $steamData['title'] ?? null,
                'description' => $steamData['description'] ?? null,
                'genre' => $steamData['genre'] ?? null,
                'release_date' => $steamData['release_date'] ?? null,
                'cover_image' => $steamData['cover_image'] ?? null,
                'popularity' => $steamData['popularity'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');

            $game->fill($metadata);
        }

        $offerData = null;
        if (isset($steamData['price'])) {
            $offerData = [
                ...$steamData['price'],
                'platform' => 'Steam',
                'source' => 'steam',
                'store_url' => 'https://store.steampowered.com/app/'.(int) $game->steam_app_id.'/',
            ];
        } else {
            $offerData = $cheapShark->fetchCheapestDeal((int) $game->steam_app_id);

            if ($offerData !== null) {
                $game->fill(array_filter([
                    'title' => $offerData['title'] ?? null,
                    'cover_image' => $offerData['cover_image'] ?? null,
                ], static fn ($value) => $value !== null && $value !== ''));
            }
        }

        if ($offerData === null) {
            if ($steamData !== null) {
                $game->last_synced_at = now();
                $game->save();
            }

            $this->error('No current price was returned by Steam or the single-game CheapShark fallback. Existing offers were left unchanged.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($game, $offerData, $steamData): void {
                $game->last_synced_at = now();
                $game->save();

                foreach ($steamData['system_requirements'] ?? [] as $type => $specification) {
                    $game->systemRequirements()->updateOrCreate(
                        ['requirement_type' => $type],
                        $specification,
                    );
                }

                $offer = GameOffer::query()->updateOrCreate(
                    ['game_id' => $game->id, 'platform' => $offerData['platform']],
                    [
                        'source' => $offerData['source'],
                        'currency' => $offerData['currency'],
                        'original_price' => $offerData['original_price'],
                        'discount_percentage' => $offerData['discount_percentage'],
                        'final_price' => $offerData['final_price'],
                        'store_url' => $offerData['store_url'],
                    ],
                );

                $latestRealPoint = $offer->priceHistories()
                    ->where('is_simulated', false)
                    ->latest('recorded_at')
                    ->first();

                if (! $latestRealPoint
                    || (float) $latestRealPoint->final_price !== (float) $offerData['final_price']
                    || (int) $latestRealPoint->discount_percentage !== (int) $offerData['discount_percentage']) {
                    $offer->priceHistories()->create([
                        'original_price' => $offerData['original_price'],
                        'discount_percentage' => $offerData['discount_percentage'],
                        'final_price' => $offerData['final_price'],
                        'recorded_at' => now(),
                        'is_simulated' => false,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Database write failed; no live offer was published to the API.');

            return self::FAILURE;
        }

        $this->info("Saved {$offerData['platform']} price for {$game->title} to MySQL ({$offerData['currency']}). The frontend API can now serve it.");

        return self::SUCCESS;
    }
}
