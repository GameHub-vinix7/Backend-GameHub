<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameOffer;
use App\Services\EpicStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncEpicGameDataCommand extends Command
{
    protected $signature = 'games:sync-epic
        {--game= : Sync exactly one existing game by slug; omit to sync every catalog game}';

    protected $description = 'Fetch Indonesian Epic Games Store prices and persist them as Epic offers';

    public function handle(EpicStoreService $epic): int
    {
        $slug = trim((string) $this->option('game'));

        $games = $slug !== ''
            ? Game::query()->where('slug', $slug)->get()
            : Game::query()->orderBy('id')->get();

        if ($games->isEmpty()) {
            $this->error($slug !== ''
                ? "Game '{$slug}' was not found in MySQL."
                : 'No games exist in MySQL. Seed the catalog first.');

            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $delay = $games->count() > 1;

        foreach ($games as $game) {
            $offerData = $epic->fetchGame($game->title, $game->slug);

            if ($offerData === null) {
                $this->warn("Epic: no verified IDR offer for {$game->title}; existing offer left unchanged.");
                $skipped++;
            } else {
                try {
                    $this->persist($game, $offerData);
                    $this->line(sprintf(
                        'Epic: %s — %s %s (%d%% off)',
                        $game->title,
                        $offerData['currency'],
                        number_format($offerData['final_price'], 0, ',', '.'),
                        $offerData['discount_percentage'],
                    ));
                    $updated++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error("Epic: database write failed for {$game->title}.");
                    $skipped++;
                }
            }

            // Be polite to the storefront when syncing the whole catalog.
            if ($delay) {
                usleep(750_000);
            }
        }

        $this->info("Epic sync finished: {$updated} updated, {$skipped} skipped.");

        return $updated > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function persist(Game $game, array $offerData): void
    {
        DB::transaction(function () use ($game, $offerData): void {
            $offer = GameOffer::query()->updateOrCreate(
                ['game_id' => $game->id, 'platform' => 'Epic Games'],
                [
                    'source' => 'epic',
                    'currency' => $offerData['currency'],
                    'original_price' => $offerData['original_price'],
                    'discount_percentage' => $offerData['discount_percentage'],
                    'final_price' => $offerData['final_price'],
                    'store_url' => $offerData['store_url'],
                ],
            );

            $latest = $offer->priceHistories()
                ->where('is_simulated', false)
                ->latest('recorded_at')
                ->first();

            if (! $latest
                || (float) $latest->final_price !== (float) $offerData['final_price']
                || (int) $latest->discount_percentage !== (int) $offerData['discount_percentage']) {
                $offer->priceHistories()->create([
                    'original_price' => $offerData['original_price'],
                    'discount_percentage' => $offerData['discount_percentage'],
                    'final_price' => $offerData['final_price'],
                    'recorded_at' => now(),
                    'is_simulated' => false,
                ]);
            }
        });
    }
}
