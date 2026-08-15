<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();

            $table->string('public_id')->unique();

            // El reto se juega dentro de un evento: los dos vaqueros deben
            // estar inscritos en él, aunque cada uno corra su categoría.
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            // A cada uno se le mira lo que lleva acumulado en la suya.
            $table->foreignId('challenger_participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignId('rival_participant_id')->constrained('participants')->cascadeOnDelete();

            // Se llena al resolver el reto: congela al ganador aunque después
            // se corrija algún tiempo.
            $table->foreignId('winner_participant_id')->nullable()->constrained('participants')->nullOnDelete();

            $table->string('status')->default('open')->index();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            // El mismo par no se reta dos veces en el mismo evento.
            $table->unique(
                ['tournament_id', 'challenger_participant_id', 'rival_participant_id'],
                'challenges_pair_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
