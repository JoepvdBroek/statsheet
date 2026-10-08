<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables whose Sets get a kind.
     *
     * @var list<string>
     */
    private const array TABLES = ['routine_sets', 'workout_sets'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('kind')->default('working');
            });

            DB::table($name)->where('is_warm_up', true)->update(['kind' => 'warm_up']);

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('is_warm_up');
            });
        }
    }

    /**
     * Reverse the migrations. Drop Sets become Working Sets.
     */
    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_warm_up')->default(false);
            });

            DB::table($name)->where('kind', 'warm_up')->update(['is_warm_up' => true]);

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
