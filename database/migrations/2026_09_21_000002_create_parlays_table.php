<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parlays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('Nombre descriptivo del parlay, ej: "Champions Tuesday"');
            $table->decimal('stake', 10, 2)->nullable()->comment('Monto apostado');
            $table->decimal('potential_payout', 10, 2)->nullable()->comment('Ganancia potencial calculada');
            $table->string('status')->default('pending')->comment('pending | active | won | lost | cancelled');
            $table->timestamp('settled_at')->nullable()->comment('Fecha de resolución del parlay');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parlays');
    }
};

