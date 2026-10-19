<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;


class Game extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'steam_app_id',
        'description',
        'genre',
        'release_date',
        'cover_image',
        'popularity',
        'last_synced_at',
    ];
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'popularity' => 'integer',
            'steam_app_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
    public function offers(): HasMany
    {
        return $this->hasMany(GameOffer::class);
    }
    public function scopeWithDealSummary(Builder $query, ?callable $offerFilters = null): Builder
    {
        $offerFilters ??= static fn (Builder $offers) => $offers->where('source', '!=', 'seed');

        return $query->withMin(['offers as lowest_price' => fn (Builder $offers) => $offerFilters($offers)], 'final_price')
            ->withMax(['offers as highest_discount' => fn (Builder $offers) => $offerFilters($offers)], 'discount_percentage');
    }
    public function systemRequirements(): HasMany
    {
        return $this->hasMany(GameSystemRequirement::class);
    }
}
