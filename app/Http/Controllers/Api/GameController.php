<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\GameOffer;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'genre' => ['sometimes', 'string', 'max:60'],
            'platform' => ['sometimes', Rule::in(['Steam', 'Epic Games', 'CheapShark', 'GOG'])],
            'min_price' => ['sometimes', 'integer', 'min:0'],
            'max_price' => ['sometimes', 'integer', 'min:0', ...($request->filled('min_price') ? ['gte:min_price'] : [])],
            'min_discount' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['price_asc', 'price_desc', 'discount_desc', 'newest', 'popular', 'title_asc'])],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $offerFilters = static function (BuilderContract $offers) use ($filters): void {
            $offers->where('source', '!=', 'seed');

            if (isset($filters['platform'])) {
                $offers->where('platform', $filters['platform']);
            }
            if (isset($filters['min_price'])) {
                $offers->where('final_price', '>=', $filters['min_price']);
            }
            if (isset($filters['max_price'])) {
                $offers->where('final_price', '<=', $filters['max_price']);
            }
            if (isset($filters['min_discount'])) {
                $offers->where('discount_percentage', '>=', $filters['min_discount']);
            }
        };

        $query = Game::query()
            ->whereHas('offers', $offerFilters)
            ->with(['offers' => $offerFilters])
            ->withDealSummary($offerFilters);

        if (isset($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }
        if (isset($filters['genre'])) {
            $query->where('genre', $filters['genre']);
        }

        $sort = $filters['sort'] ?? 'discount_desc';
        match ($sort) {
            'price_asc' => $query->orderBy('lowest_price')->orderBy('title'),
            'price_desc' => $query->orderByDesc('lowest_price')->orderBy('title'),
            'newest' => $query->orderByDesc('release_date')->orderBy('title'),
            'popular' => $query->orderByDesc('popularity')->orderBy('title'),
            'title_asc' => $query->orderBy('title'),
            default => $query->orderByDesc('highest_discount')->orderBy('title'),
        };

        return GameResource::collection($query->paginate($filters['per_page'] ?? 12)->withQueryString());
    }

    public function show(string $slug): GameResource
    {
        $game = Game::with([
            'offers' => fn ($offers) => $offers->where('source', '!=', 'seed'),
            'systemRequirements',
        ])
            ->withDealSummary()
            ->where('slug', $slug)
            ->whereHas('offers', fn ($offers) => $offers->where('source', '!=', 'seed'))
            ->firstOrFail();

        return new GameResource($game);
    }

    public function genres()
    {
        return response()->json(Game::query()->whereHas('offers', fn (Builder $q) => $q->where('source', '!=', 'seed'))
            ->distinct()->orderBy('genre')->pluck('genre'));
    }

    public function platforms()
    {
        return response()->json(GameOffer::query()
            ->where('source', '!=', 'seed')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform'));
    }
}
