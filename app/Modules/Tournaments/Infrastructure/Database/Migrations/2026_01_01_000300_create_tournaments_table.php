<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('location')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('status')->default('draft')->index();
            $table->string('cover_image_path')->nullable();

            $table->timestamps();

            $table->index(['status', 'start_date']);
        });

        Schema::create('tournament_categories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);

            // Estructura competitiva de la categoría: días × rondas por día.
            $table->unsignedSmallInteger('number_of_days')->default(1);
            $table->unsignedSmallInteger('rounds_per_day')->default(1);

            $table->string('status')->default('pending')->index();

            $table->timestamps();

            // Una categoría no puede repetirse dentro del mismo torneo.
            $table->unique(['tournament_id', 'slug']);
            $table->index(['tournament_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_categories');
        Schema::dropIfExists('tournaments');
    }
};
