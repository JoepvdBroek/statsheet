<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables whose Exercises can be grouped into Supersets.
     *
     * @var list<string>
     */
    private const array TABLES = ['routine_exercises', 'workout_exercises'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedTinyInteger('superset')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations. Supersets are dissolved.
     */
    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('superset');
            });
        }
    }
};
