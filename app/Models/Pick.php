<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pick extends Model
{
    use HasFactory;

    protected $fillable = [
        'parlay_id',
        'event_id',
        'pick_type',
        'condition',
        'target_value',
        'current_progress',
        'progress_percentage',
        'status',
        'odd',
    ];

    protected $casts = [
        'target_value'        => 'decimal:2',
        'current_progress'    => 'decimal:2',
        'progress_percentage' => 'decimal:2',
        'odd'                 => 'decimal:2',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    public function parlay(): BelongsTo
    {
        return $this->belongsTo(Parlay::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // ─── Lógica de Progreso ─────────────────────────────────────────────────────

    /**
     * Calcula el valor de progreso actual según el tipo de pick y el evento.
     * Retorna el valor numérico de progreso actual.
     */
    public function calculateCurrentProgress(): float
    {
        $event = $this->event;

        return match ($this->pick_type) {
            'total_goals'  => (float) $event->totalGoals(),
            'home_score'   => (float) $event->home_score,
            'away_score'   => (float) $event->away_score,
            'result'       => $this->condition === $event->currentResult() ? 1.0 : 0.0,
            default        => 0.0,
        };
    }

    /**
     * Calcula el porcentaje visual para la barra de progreso (0–100).
     *
     * Para picks de tipo "over": progreso / target * 100 (cappado a 100).
     * Para picks de tipo "under": inverso — cuánto falta para que NO se cumpla.
     * Para picks de tipo "result": 0 o 100 directamente.
     */
    public function calculateProgressPercentage(float $currentProgress): float
    {
        if ((float) $this->target_value === 0.0) {
            return 0.0;
        }

        return match ($this->condition) {
            'over'  => min(100.0, round(($currentProgress / (float) $this->target_value) * 100, 2)),
            'under' => $currentProgress < (float) $this->target_value
                ? min(100.0, round((1 - ($currentProgress / (float) $this->target_value)) * 100, 2))
                : 0.0,
            'home', 'away', 'draw' => $currentProgress >= 1.0 ? 100.0 : 0.0,
            'exact' => $currentProgress === (float) $this->target_value ? 100.0 : min(
                100.0,
                round(($currentProgress / (float) $this->target_value) * 100, 2)
            ),
            default => 0.0,
        };
    }

    /**
     * Evalúa si el pick ha sido ganado o perdido según el estado actual del evento.
     * Solo resuelve picks cuando el evento ha finalizado.
     */
    public function evaluateStatus(float $currentProgress): string
    {
        $event = $this->event;

        // Solo resolver cuando el partido termina (para no cerrar prematuramente)
        if (! $event->isFinished()) {
            return 'pending';
        }

        return match ($this->condition) {
            'over'         => $currentProgress > (float) $this->target_value ? 'won' : 'lost',
            'under'        => $currentProgress < (float) $this->target_value ? 'won' : 'lost',
            'exact'        => $currentProgress === (float) $this->target_value ? 'won' : 'lost',
            'home', 'away', 'draw' => $currentProgress >= 1.0 ? 'won' : 'lost',
            default        => 'pending',
        };
    }
}

