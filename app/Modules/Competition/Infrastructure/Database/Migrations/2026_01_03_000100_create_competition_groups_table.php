<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_groups', function (Blueprint $table): void {
            $table->id();

            // El grupo de salida pertenece al evento: no es una propiedad
            // permanente del participante ni de la categoría.
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('order')->default(0);

            $table->timestamps();

            // El orden de salida se define una vez por evento.
            $table->unique(['tournament_id', 'name']);
            $table->index(['tournament_id', 'order']);
        });

        Schema::table('registrations', function (Blueprint $table): void {
            // La inscripción define en qué grupo sale el vaquero dentro de
            // esta competencia; otro torneo puede usar otro grupo.
            $table->foreignId('competition_group_id')
                ->nullable()
                ->after('horse_id')
                ->constrained('competition_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('competition_group_id');
        });

        Schema::dropIfExists('competition_groups');
    }
};
