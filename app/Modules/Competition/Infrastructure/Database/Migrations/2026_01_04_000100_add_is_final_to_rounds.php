<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table): void {
            // El lazo final del último día: solo lo corre el top 10 de la
            // categoría y su resultado sí suma al acumulado.
            $table->boolean('is_final')->default(false)->after('round_number');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table): void {
            $table->dropColumn('is_final');
        });
    }
};
