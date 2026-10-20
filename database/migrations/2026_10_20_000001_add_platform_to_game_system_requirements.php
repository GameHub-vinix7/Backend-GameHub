<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'game_system_requirements';
        $columns = Schema::getColumnListing($tableName);

        if (! in_array('platform', $columns, true)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('platform')->default('Steam')->after('game_id'));
        }
        if (! in_array('other', $columns, true)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->text('other')->nullable()->after('sound_card'));
        }

        foreach (['memory', 'storage'] as $column) {
            if (Schema::getColumnType($tableName, $column) !== 'text') {
                Schema::table($tableName, fn (Blueprint $table) => $table->text($column)->nullable()->change());
            }
        }

        // InnoDB may be using the composite unique key to satisfy the game_id FK.
        // Add an independent FK index before removing that key.
        if ($this->findIndex($tableName, ['game_id']) === null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index('game_id'));
        }

        $oldUnique = $this->findIndex($tableName, ['game_id', 'requirement_type'], true);
        if ($oldUnique !== null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($oldUnique['name']));
        }

        if ($this->findIndex($tableName, ['game_id', 'platform', 'requirement_type'], true) === null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unique(['game_id', 'platform', 'requirement_type'], 'gsr_game_platform_type_unique'));
        }
    }

    public function down(): void
    {
        $tableName = 'game_system_requirements';
        if (Schema::hasColumn($tableName, 'platform')) {
            DB::table($tableName)->where('platform', '!=', 'Steam')->delete();
        }

        $newUnique = $this->findIndex($tableName, ['game_id', 'platform', 'requirement_type'], true);
        if ($newUnique !== null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($newUnique['name']));
        }

        if ($this->findIndex($tableName, ['game_id', 'requirement_type'], true) === null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unique(['game_id', 'requirement_type']));
        }

        $gameIdIndex = $this->findIndex($tableName, ['game_id'], false);
        if ($gameIdIndex !== null) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($gameIdIndex['name']));
        }

        $columns = Schema::getColumnListing($tableName);
        $dropColumns = array_values(array_intersect(['platform', 'other'], $columns));
        if ($dropColumns !== []) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($dropColumns));
        }

        foreach (['memory', 'storage'] as $column) {
            if (Schema::getColumnType($tableName, $column) === 'text') {
                Schema::table($tableName, fn (Blueprint $table) => $table->string($column, 255)->nullable()->change());
            }
        }
    }

    private function findIndex(string $tableName, array $columns, ?bool $unique = null): ?array
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['columns'] ?? []) === $columns
                && ($unique === null || (bool) ($index['unique'] ?? false) === $unique)) {
                return $index;
            }
        }

        return null;
    }
};
