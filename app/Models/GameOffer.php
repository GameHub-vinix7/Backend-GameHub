<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameOffer extends Model
{
    protected $fillable = [
        'game_id',
        'platform',
        'source',
        'currency',
        'original_price',
        'discount_percentage',
        'final_price',
        'store_url',
    ];
    public function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'discount_percentage' => 'integer',
            'final_price' => 'decimal:2',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
    public function priceHistories(): HasMany
    {
        return $this->hasMany(GameOfferHistory::class);
    }
}
