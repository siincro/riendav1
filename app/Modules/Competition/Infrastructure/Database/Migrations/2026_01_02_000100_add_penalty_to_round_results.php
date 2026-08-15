<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_results', function (Blueprint $table): void {
            // El castigo que el juez suma al lazo, en milisegundos enteros
            // como el propio tiempo. Cero cuando no hubo penalización.
            $table->unsignedInteger('penalty_ms')->default(0)->after('time_ms');
        });
    }

    public function down(): void
    {
        Schema::table('round_results', function (Blueprint $table): void {
            $table->dropColumn('penalty_ms');
        });
    }
};
