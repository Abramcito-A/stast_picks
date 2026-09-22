<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique()->nullable()->comment('ID del partido en la API deportiva externa');
            $table->string('sport')->default('soccer')->comment('Tipo de deporte: soccer, basketball, etc.');
            $table->string('league')->nullable()->comment('Liga o competición');
            $table->string('home_team');
            $table->string('away_team');
            $table->unsignedTinyInteger('home_score')->default(0);
            $table->unsignedTinyInteger('away_score')->default(0);
            $table->string('status')->default('scheduled')->comment('scheduled | live | finished | postponed');
            $table->unsignedSmallInteger('elapsed_minutes')->nullable()->comment('Minutos jugados (para fútbol)');
            $table->timestamp('starts_at')->nullable()->comment('Fecha y hora de inicio del partido');
            $table->json('raw_data')->nullable()->comment('Respuesta completa de la API para referencia');
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

