<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedBigInteger('steam_app_id')->nullable()->unique();
            $table->timestamp('last_synced_at')->nullable();
        });

        Schema::table('game_offers', function (Blueprint $table): void {
            $table->char('currency', 3)->default('IDR');
            $table->string('source')->default('seed');
            $table->decimal('original_price', 14, 2)->change();
            $table->decimal('final_price', 14, 2)->change();
        });

        Schema::table('game_offer_histories', function (Blueprint $table): void {
            $table->decimal('original_price', 14, 2)->change();
            $table->decimal('final_price', 14, 2)->change();
        });

        $steamAppIds = [
            'hades' => 1145360,
            'stardew-valley' => 413150,
            'cyberpunk-2077' => 1091500,
            'control-ultimate-edition' => 870780,
            'the-witcher-3-wild-hunt' => 292030,
            'celeste' => 504230,
            'dead-cells' => 588650,
            'sifu' => 2138710,
        ];

        foreach ($steamAppIds as $slug => $steamAppId) {
            DB::table('games')->where('slug', $slug)->update(['steam_app_id' => $steamAppId]);
        }
    }

    public function down(): void
    {
        Schema::table('game_offer_histories', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_price')->change();
            $table->unsignedBigInteger('final_price')->change();
        });

        Schema::table('game_offers', function (Blueprint $table): void {
            $table->dropColumn(['currency', 'source']);
            $table->unsignedBigInteger('original_price')->change();
            $table->unsignedBigInteger('final_price')->change();
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->dropUnique(['steam_app_id']);
            $table->dropColumn(['steam_app_id', 'last_synced_at']);
        });
    }
};
