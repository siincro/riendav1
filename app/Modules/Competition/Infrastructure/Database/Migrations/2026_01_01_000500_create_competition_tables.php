<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_days', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tournament_category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->date('date')->nullable();
            $table->string('status')->default('pending')->index();

            $table->timestamps();

            // Un día pertenece a una sola categoría y no se repite dentro de ella.
            $table->unique(['tournament_category_id', 'day_number']);
        });

        Schema::create('rounds', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('competition_day_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->string('status')->default('pending')->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            // Una ronda pertenece a un solo día y no se repite dentro de él.
            $table->unique(['competition_day_id', 'round_number']);
        });

        Schema::create('round_results', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();

            // El tiempo se almacena SIEMPRE como entero en milisegundos.
            // Nunca como FLOAT: 8.342 s se persiste como 8342.
            $table->unsignedInteger('time_ms')->default(0);

            // Ronda fallida => successful = false y time_ms = 0.
            $table->boolean('successful')->default(true);

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Un inscrito tiene exactamente un resultado por ronda.
            $table->unique(['round_id', 'registration_id']);
            $table->index(['registration_id', 'successful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_results');
        Schema::dropIfExists('rounds');
        Schema::dropIfExists('competition_days');
    }
};
