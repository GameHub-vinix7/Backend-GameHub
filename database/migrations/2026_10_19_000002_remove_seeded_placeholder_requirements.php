<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('game_system_requirements')
            ->where('operating_system', 'Windows 10 64-bit')
            ->where('processor', 'Intel Core i5-2500K / AMD FX-6300')
            ->where('memory', '8 GB RAM')
            ->where('graphics', 'NVIDIA GeForce GTX 770 2GB / AMD Radeon R9 280 3GB')
            ->where('storage', '150 GB ruang tersedia')
            ->delete();

        DB::table('game_system_requirements')
            ->where('operating_system', 'Windows 10 64-bit')
            ->where('processor', 'Intel Core i7-4770K / AMD Ryzen 5 1500X')
            ->where('memory', '12 GB RAM')
            ->where('graphics', 'NVIDIA GeForce GTX 1060 6GB / AMD Radeon RX 480 4GB')
            ->where('storage', '150 GB ruang tersedia')
            ->delete();
    }

    public function down(): void
    {
        // Placeholder requirements are intentionally not restored on rollback.
    }
};
