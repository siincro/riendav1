<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estas dos tablas son resultados DERIVADOS: se reconstruyen íntegramente
     * a partir de `round_results`. Existen para que la clasificación en vivo y
     * el historial se consulten en una sola lectura, no como fuente de verdad.
     */
    public function up(): void
    {
        Schema::create('daily_results', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('competition_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // SUM(time_ms) de las rondas acertadas del día.
            $table->unsignedInteger('time_ms')->default(0);

            // COUNT de rondas acertadas del día.
            $table->unsignedSmallInteger('successful_rounds')->default(0);

            // Rondas con resultado registrado, acertadas o no.
            $table->unsignedSmallInteger('recorded_rounds')->default(0);

            $table->timestamps();

            $table->unique(['competition_day_id', 'registration_id']);
        });

        Schema::create('category_standings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tournament_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('total_time_ms')->default(0);
            $table->unsignedSmallInteger('total_successful_rounds')->default(0);
            $table->unsignedSmallInteger('recorded_rounds')->default(0);

            // Posición con empates compartidos: 1, 2, 2, 4.
            $table->unsignedSmallInteger('position')->nullable();
            $table->boolean('tied')->default(false);

            $table->timestamps();

            $table->unique(['tournament_category_id', 'registration_id']);

            // Índice que sirve directamente el orden de la clasificación.
            $table->index(['tournament_category_id', 'total_successful_rounds', 'total_time_ms'], 'standings_ranking_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_standings');
        Schema::dropIfExists('daily_results');
    }
};
