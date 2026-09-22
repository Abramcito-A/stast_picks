<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\SportsApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job que consulta la API de deportes y actualiza los eventos en BD.
 * Se ejecuta cada minuto via el scheduler (routes/console.php).
 *
 * Flujo:
 * 1. Consulta los partidos en vivo a la API.
 * 2. Para cada partido, busca o crea el evento en la BD.
 * 3. Si el marcador cambió, actualiza el evento.
 * 4. Despacha EvaluatePicksJob para recalcular los picks afectados.
 */
class FetchSportsDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('sports-data');
    }

    public function handle(SportsApiService $sportsApi): void
    {
        Log::info('FetchSportsDataJob: consultando SportsData.io para la fecha de hoy');

        // SportsData.io no tiene endpoint "live global" — consultamos los
        // partidos del día y procesamos todos (scheduled, live, finished).
        $todayGames = $sportsApi->getFixturesByDate(now()->format('Y-m-d'));

        if (empty($todayGames)) {
            Log::info('FetchSportsDataJob: no hay partidos hoy en la competición configurada');

            return;
        }

        $updatedCount = 0;

        foreach ($todayGames as $game) {
            $mappedData = $sportsApi->mapFixtureToEvent($game);

            if (empty($mappedData['external_id'])) {
                continue;
            }

            /** @var \App\Models\Event $event */
            $event = \App\Models\Event::firstOrNew(['external_id' => $mappedData['external_id']]);

            // Detectar cambio real de marcador o estado antes de actualizar
            $scoreChanged = $event->exists && (
                $event->home_score !== $mappedData['home_score'] ||
                $event->away_score !== $mappedData['away_score'] ||
                $event->status     !== $mappedData['status']
            );

            $isNew = ! $event->exists;
            $event->fill($mappedData)->save();

            // Solo disparar evaluación si hay algo que haya cambiado
            if ($scoreChanged || ($isNew && $mappedData['status'] === 'live')) {
                EvaluatePicksJob::dispatch($event->id);
                $updatedCount++;
            }
        }

        Log::info("FetchSportsDataJob: {$updatedCount} eventos actualizados, picks en evaluación");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('FetchSportsDataJob: falló con excepción', [
            'message' => $exception->getMessage(),
        ]);
    }
}
