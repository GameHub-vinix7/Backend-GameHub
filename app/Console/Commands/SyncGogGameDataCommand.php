<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameOffer;
use App\Services\GogStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncGogGameDataCommand extends Command
{
    protected $signature = 'games:sync-gog {--game= : Sync exactly one existing game by catalog slug}';

    protected $description = 'Fetch one existing game price from GOG and persist the verified IDR offer';

    public function handle(GogStoreService $gog): int
    {
        $slug = trim((string) $this->option('game'));
        if ($slug === '') {
            $this->error('Specify exactly one catalog game: --game=<slug>. Bulk synchronization is intentionally disabled.');

            return self::INVALID;
        }

        $game = Game::query()->where('slug', $slug)->first();
        if (! $game) {
            $this->error("Game '{$slug}' was not found in the local catalog. Add its game identity first.");

            return self::FAILURE;
        }

        $offerData = $gog->fetchGame($game->title);
        if ($offerData === null) {
            $this->error("GOG returned no exact match with a verified IDR price for {$game->title}; existing offers were left unchanged.");

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($game, $offerData): void {
                foreach ($offerData['system_requirements'] ?? [] as $type => $specification) {
                    $game->systemRequirements()->updateOrCreate(
                        ['platform' => 'GOG', 'requirement_type' => $type],
                        $specification,
                    );
                }

                $offer = GameOffer::query()->updateOrCreate(
                    ['game_id' => $game->id, 'platform' => 'GOG'],
                    [
                        'source' => 'gog',
                        'currency' => $offerData['currency'],
                        'original_price' => $offerData['original_price'],
                        'discount_percentage' => $offerData['discount_percentage'],
                        'final_price' => $offerData['final_price'],
                        'store_url' => $offerData['store_url'],
                    ],
                );

                $latest = $offer->priceHistories()->where('is_simulated', false)->latest('recorded_at')->first();
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
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Database write failed; no GOG offer was published to the API.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Saved GOG price for %s: IDR %s (%d%% off).',
            $game->title,
            number_format($offerData['final_price'], 0, ',', '.'),
            $offerData['discount_percentage'],
        ));

        return self::SUCCESS;
    }
}
