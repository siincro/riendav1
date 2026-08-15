<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table): void {
            $table->id();

            // Identificador público permanente (RND-000184). El id interno nunca se expone.
            $table->string('public_id')->unique();

            $table->string('first_name');
            $table->string('last_name');

            // El documento identifica de forma única a la persona y es la
            // defensa principal contra participantes duplicados.
            $table->string('document')->nullable()->unique();

            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('photo_path')->nullable();

            $table->string('status')->default('active')->index();

            // Fase posterior: el participante reclama su perfil.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
