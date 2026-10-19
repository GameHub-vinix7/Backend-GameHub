<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameOfferHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GamePriceHistoryController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $filters = $request->validate([
            'platform' => ['sometimes', Rule::in(['Steam', 'Epic Games', 'CheapShark'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);

        $game = Game::query()->where('slug', $slug)->firstOrFail();

        $history = GameOfferHistory::query()
            ->whereHas('offer', function (Builder $offers) use ($game, $filters): void {
                $offers->where('game_id', $game->id);
                $offers->where('source', '!=', 'seed');

                if (isset($filters['platform'])) {
                    $offers->where('platform', $filters['platform']);
                }
            })
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('recorded_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('recorded_at', '<=', $filters['to']))
            ->where('is_simulated', false)
            ->with('offer:id,game_id,platform,currency')
            ->orderBy('recorded_at')
            ->get();

        $series = $history->groupBy(fn (GameOfferHistory $point) => $point->offer->platform)
            ->map(fn ($points, $platform) => [
                'platform' => $platform,
                'currency' => $points->first()->offer->currency,
                'points' => $points->map(fn (GameOfferHistory $point) => [
                    'recorded_at' => $point->recorded_at->toIso8601String(),
                    'original_price' => (float) $point->original_price,
                    'discount_percentage' => $point->discount_percentage,
                    'final_price' => (float) $point->final_price,
                    'is_simulated' => $point->is_simulated,
                ])->values(),
            ])->values();

        return response()->json([
            'game' => ['slug' => $game->slug, 'title' => $game->title],
            'currency' => $series->count() === 1 ? $series->first()['currency'] : null,
            'data_is_simulated' => $history->contains(fn (GameOfferHistory $point) => $point->is_simulated),
            'series' => $series,
        ]);
    }
}
