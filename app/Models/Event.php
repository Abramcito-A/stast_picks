<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'sport',
        'league',
        'home_team',
        'away_team',
        'home_score',
        'away_score',
        'status',
        'elapsed_minutes',
        'starts_at',
        'raw_data',
    ];

    protected $casts = [
        'starts_at'       => 'datetime',
        'home_score'      => 'integer',
        'away_score'      => 'integer',
        'elapsed_minutes' => 'integer',
        'raw_data'        => 'array',
    ];

    // ─── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Los picks que dependen de este evento para actualizarse.
     */
    public function picks(): HasMany
    {
        return $this->hasMany(Pick::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Total de goles del partido (home + away).
     */
    public function totalGoals(): int
    {
        return $this->home_score + $this->away_score;
    }

    /**
     * Resultado actual del partido ('home' | 'away' | 'draw').
     */
    public function currentResult(): string
    {
        if ($this->home_score > $this->away_score) {
            return 'home';
        }

        if ($this->away_score > $this->home_score) {
            return 'away';
        }

        return 'draw';
    }

    /**
     * Indica si el partido está en progreso.
     */
    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    /**
     * Indica si el partido ya finalizó.
     */
    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }
}

