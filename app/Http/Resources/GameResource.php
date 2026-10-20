<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'genre' => $this->genre,
            'release_date' => $this->release_date?->toDateString(),
            'cover_image' => $this->cover_image,
            'popularity' => $this->popularity,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'lowest_price' => $this->lowest_price === null ? null : (float) $this->lowest_price,
            'highest_discount' => $this->highest_discount === null ? null : (int) $this->highest_discount,
            'offers' => $this->whenLoaded('offers', fn () => $this->offers->map(fn ($offer) => [
                'platform' => $offer->platform,
                'source' => $offer->source,
                'original_price' => (float) $offer->original_price,
                'discount_percentage' => $offer->discount_percentage,
                'final_price' => (float) $offer->final_price,
                'currency' => $offer->currency,
                'store_url' => $offer->store_url,
                'updated_at' => $offer->updated_at?->toIso8601String(),
            ])->values()),
            'system_requirements' => $this->whenLoaded(
                'systemRequirements',
                fn () => $this->systemRequirements->map(fn ($requirement) => [
                    'platform' => $requirement->platform,
                    'type' => $requirement->requirement_type,
                    'operating_system' => $requirement->operating_system,
                    'processor' => $requirement->processor,
                    'memory' => $requirement->memory,
                    'graphics' => $requirement->graphics,
                    'network' => $requirement->network,
                    'storage' => $requirement->storage,
                    'sound_card' => $requirement->sound_card,
                    'other' => $requirement->other,
                ])->values(),
            ),
        ];
    }
}
