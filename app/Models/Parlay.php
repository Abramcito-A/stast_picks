<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parlay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'stake',
        'potential_payout',
        'status',
        'settled_at',
    ];

    protected $casts = [
        'stake'            => 'decimal:2',
        'potential_payout' => 'decimal:2',
        'settled_at'       => 'datetime',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Cuántos picks están ganando actualmente.
     */
    public function winningPicksCount(): int
    {
        return $this->picks()->where('status', 'won')->count();
    }

    /**
     * Cuántos picks están pendientes.
     */
    public function pendingPicksCount(): int
    {
        return $this->picks()->where('status', 'pending')->count();
    }

    /**
     * Progreso general del parlay como porcentaje (promedio de todos los picks).
     */
    public function overallProgressPercentage(): float
    {
        $picks = $this->picks;

        if ($picks->isEmpty()) {
            return 0.0;
        }

        return round($picks->avg('progress_percentage'), 2);
    }

    /**
     * Calcula y actualiza el estado global del parlay
     * basándose en el estado de sus picks individuales.
     */
    public function recalculateStatus(): void
    {
        $picks = $this->picks;

        if ($picks->isEmpty()) {
            return;
        }

        if ($picks->contains('status', 'lost')) {
            $this->update(['status' => 'lost', 'settled_at' => now()]);

            return;
        }

        if ($picks->every(fn ($p) => $p->status === 'won')) {
            $this->update(['status' => 'won', 'settled_at' => now()]);

            return;
        }

        $this->update(['status' => 'active']);
    }
}

