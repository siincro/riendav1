<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los resultados oficiales son información sensible: toda modificación
     * queda registrada con quién, qué, antes y después.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
