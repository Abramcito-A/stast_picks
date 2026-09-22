<?php

namespace App\Jobs;

use App\Events\PickProgressUpdated;
use App\Models\Event;
use App\Models\Pick;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job que recalcula el progreso de todos los picks ligados a un evento.
 * Se despacha automáticamente desde FetchSportsDataJob cuando el marcador cambia.
 *
 * Flujo:
 * 1. Carga el evento actualizado con sus picks (y sus parlays/usuarios).
 * 2. Para cada pick, recalcula current_progress y progress_percentage.
 * 3. Si el valor cambió, guarda en BD y emite PickProgressUpdated vía Reverb.
 * 4. Si el evento terminó, evalúa el status final del pick y actualiza el parlay.
 */
class EvaluatePicksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private readonly int $eventId)
    {
        $this->onQueue('pick-evaluation');
    }

    public function handle(): void
    {
        /** @var Event|null $event */
        $event = Event::with(['picks.parlay'])->find($this->eventId);

        if (! $event) {
            Log::warning("EvaluatePicksJob: evento #{$this->eventId} no encontrado");

            return;
        }

        Log::info("EvaluatePicksJob: evaluando {$event->picks->count()} picks del evento #{$event->id}");

        foreach ($event->picks as $pick) {
            $this->evaluatePick($pick, $event);
        }
    }

    private function evaluatePick(Pick $pick, Event $event): void
    {
        // Calcular nuevo progreso
        $newProgress    = $pick->calculateCurrentProgress();
        $newPercentage  = $pick->calculateProgressPercentage($newProgress);
        $newStatus      = $pick->evaluateStatus($newProgress);

        // Detectar si hay cambio real para evitar broadcasts innecesarios
        $progressChanged = (float) $pick->current_progress !== $newProgress
            || (float) $pick->progress_percentage !== $newPercentage
            || $pick->status !== $newStatus;

        if (! $progressChanged) {
            return;
        }

        // Actualizar pick en BD
        $pick->update([
            'current_progress'    => $newProgress,
            'progress_percentage' => $newPercentage,
            'status'              => $newStatus,
        ]);

        Log::debug("EvaluatePicksJob: pick #{$pick->id} actualizado", [
            'progress'   => $newProgress,
            'percentage' => $newPercentage,
            'status'     => $newStatus,
        ]);

        // Si el pick terminó (won/lost), recalcular el estado del parlay
        if (in_array($newStatus, ['won', 'lost'])) {
            $pick->parlay->recalculateStatus();
        }

        // Emitir evento WebSocket a Angular vía Laravel Reverb
        // Reload del parlay para tener el status actualizado en el payload
        $pick->load('parlay', 'event');
        broadcast(new PickProgressUpdated($pick))->toOthers();

        Log::info("EvaluatePicksJob: broadcast emitido para pick #{$pick->id} → usuario #{$pick->parlay->user_id}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("EvaluatePicksJob: falló para evento #{$this->eventId}", [
            'message' => $exception->getMessage(),
        ]);
    }
}
