<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $clientId = $this->clientId($request);
        $items = WishlistItem::query()
            ->where('client_id', $clientId)
            ->with(['game' => function ($games): void {
                $games->with(['offers' => fn ($offers) => $offers->where('source', '!=', 'seed')])
                    ->withDealSummary();
            }])
            ->latest()
            ->get();

        return GameResource::collection($items->pluck('game')->filter()->values());
    }

    public function store(Request $request): JsonResponse
    {
        $clientId = $this->clientId($request);
        $validated = $request->validate([
            'slug' => ['required', 'string', 'exists:games,slug'],
        ]);

        $game = Game::query()
            ->with(['offers' => fn ($offers) => $offers->where('source', '!=', 'seed')])
            ->withDealSummary()
            ->where('slug', $validated['slug'])
            ->firstOrFail();

        WishlistItem::query()->firstOrCreate([
            'client_id' => $clientId,
            'game_id' => $game->id,
        ]);

        return response()->json([
            'message' => 'Game tersimpan di wishlist.',
            'data' => new GameResource($game),
        ], 201);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $clientId = $this->clientId($request);
        $game = Game::query()->where('slug', $slug)->firstOrFail();

        $deleted = WishlistItem::query()
            ->where('client_id', $clientId)
            ->where('game_id', $game->id)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Game tidak ada di wishlist.'], 404);
        }

        return response()->json(['message' => 'Game dihapus dari wishlist.']);
    }

    private function clientId(Request $request): string
    {
        return Validator::make(
            ['client_id' => $request->header('X-Client-ID')],
            ['client_id' => ['required', 'uuid']],
        )->validate()['client_id'];
    }
}
