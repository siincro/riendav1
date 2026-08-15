<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tournament_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();

            // La inscripción determina con qué caballo compite el jinete
            // en esta categoría concreta.
            $table->foreignId('horse_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('registration_number');
            $table->string('status')->default('active')->index();

            $table->timestamps();

            // Regla del MVP: un participante no puede tener dos inscripciones
            // dentro de la misma categoría.
            $table->unique(['tournament_category_id', 'participant_id']);

            // El dorsal es único dentro de la categoría.
            $table->unique(['tournament_category_id', 'registration_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
