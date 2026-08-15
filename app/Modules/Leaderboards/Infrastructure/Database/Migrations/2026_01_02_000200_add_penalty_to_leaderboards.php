<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El acumulado ya incluye la penalización dentro del tiempo; estas columnas
     * guardan cuánto de ese tiempo es castigo, para poder explicarlo sin
     * recorrer los lazos uno por uno.
     */
    public function up(): void
    {
        Schema::table('daily_results', function (Blueprint $table): void {
            $table->unsignedInteger('penalty_ms')->default(0)->after('time_ms');
        });

        Schema::table('category_standings', function (Blueprint $table): void {
            $table->unsignedInteger('total_penalty_ms')->default(0)->after('total_time_ms');
        });
    }

    public function down(): void
    {
        Schema::table('daily_results', function (Blueprint $table): void {
            $table->dropColumn('penalty_ms');
        });

        Schema::table('category_standings', function (Blueprint $table): void {
            $table->dropColumn('total_penalty_ms');
        });
    }
};
