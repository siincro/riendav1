<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horses', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();

            // El caballo puede existir sin jinete asociado; la asociación se
            // usa para listar "los caballos de este jinete" al inscribirlo.
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();

            // Campos mínimos exigidos por el MVP: name y status.
            $table->string('name');
            $table->string('status')->default('active')->index();

            $table->string('owner')->nullable();
            $table->string('breed')->nullable();
            $table->string('color')->nullable();
            $table->string('sex')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('photo_path')->nullable();

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horses');
    }
};
