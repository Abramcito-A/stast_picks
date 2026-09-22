<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parlay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Tipo de selección: total_goals, home_win, away_win, draw, player_goals, etc.
            $table->string('pick_type')->comment('Tipo: total_goals | home_score | away_score | result | player_goals');

            // La condición a cumplir
            $table->string('condition')->comment('Dirección: over | under | exact | home | away | draw');

            // Valor objetivo que debe alcanzar el progress (ej: 2.5 goles)
            $table->decimal('target_value', 8, 2)->comment('Meta a alcanzar, ej: 2.5');

            // Progreso actual calculado desde el evento (ej: 1 gol actual)
            $table->decimal('current_progress', 8, 2)->default(0)->comment('Progreso actual hacia el target');

            // Porcentaje de completitud calculado (0-100), útil para la barra de progreso
            $table->decimal('progress_percentage', 5, 2)->default(0)->comment('Porcentaje visual para la UI (0-100)');

            // Estado del pick individual
            $table->string('status')->default('pending')->comment('pending | won | lost | void');

            // Cuota/odd de este pick
            $table->decimal('odd', 8, 2)->nullable()->comment('Cuota decimal, ej: 1.85');

            $table->timestamps();

            $table->index(['parlay_id', 'status']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picks');
    }
};

