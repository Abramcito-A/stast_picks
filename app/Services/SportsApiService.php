<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para consumir la API de SportsData.io (Soccer).
 *
 * Documentación: https://sportsdata.io/developers/api-documentation/soccer
 * Autenticación: Header "Ocp-Apim-Subscription-Key: {API_KEY}"
 * Base URL: https://api.sportsdata.io/v4/soccer/scores/json
 *
 * Configurar en .env:
 *   SPORTS_API_KEY=6cf098585c884d92bd1b1a6dcb969e09
 *   SPORTS_API_BASE_URL=https://api.sportsdata.io/v4/soccer/scores/json
 *   SPORTS_API_COMPETITION=UCL
 *
 * Formato de respuesta verificado (GamesByDate):
 * {
 *   "GameId": 122441,
 *   "Status": "Final" | "InProgress" | "Scheduled" | "Suspended" | "Canceled",
 *   "Clock": null | "74'" | "90+2'",
 *   "HomeTeamName": "FC Barcelona",
 *   "AwayTeamName": "Feyenoord Rotterdam",
 *   "HomeTeamScore": 3,
 *   "AwayTeamScore": 1,
 *   "Day": "2026-09-09T00:00:00",
 *   "DateTime": "2026-09-09T16:45:00",
 *   "HomeTeamKey": "FCB",
 *   "AwayTeamKey": "FEY",
 *   "Winner": "Home" | "Away" | "Draw"
 * }
 */
class SportsApiService
{
    private string $baseUrl;
    private string $apiKey;
    private string $competition;

    public function __construct()
    {
        $this->baseUrl     = config('services.sports_api.base_url', 'https://api.sportsdata.io/v4/soccer/scores/json');
        $this->apiKey      = config('services.sports_api.key', '');
        $this->competition = config('services.sports_api.competition', 'UCL');
    }

    /**
     * Obtiene los partidos del día de hoy para la competición configurada.
     * SportsData.io no tiene un endpoint de "live" global — en su lugar,
     * consultamos por fecha y filtramos los que tienen Status = "InProgress".
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLiveFixtures(): array
    {
        $today = now()->format('Y-m-d');

        $games = $this->getFixturesByDate($today);

        // Filtrar solo partidos en vivo
        return array_values(array_filter($games, fn ($g) => $this->mapStatus($g['Status'] ?? '') === 'live'));
    }

    /**
     * Obtiene los partidos de una fecha específica para la competición configurada.
     *
     * Endpoint: GET /GamesByDate/{competition}/{date}
     *
     * @param  string  $date  Formato: YYYY-MM-DD
     * @return array<int, array<string, mixed>>
     */
    public function getFixturesByDate(string $date): array
    {
        try {
            $response = $this->makeRequest("/GamesByDate/{$this->competition}/{$date}");

            if (! $response->successful()) {
                Log::warning('SportsApiService: respuesta no exitosa', [
                    'status'      => $response->status(),
                    'body'        => $response->body(),
                    'date'        => $date,
                    'competition' => $this->competition,
                ]);

                return [];
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('SportsApiService: error al obtener partidos por fecha', [
                'message'     => $e->getMessage(),
                'date'        => $date,
                'competition' => $this->competition,
            ]);

            return [];
        }
    }

    /**
     * Obtiene el detalle de un partido por su GameId externo.
     *
     * Endpoint: GET /Game/{gameId}
     *
     * @return array<string, mixed>|null
     */
    public function getFixtureById(string $externalId): ?array
    {
        try {
            $response = $this->makeRequest("/Game/{$externalId}");

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('SportsApiService: error al obtener partido por ID', [
                'message'     => $e->getMessage(),
                'external_id' => $externalId,
            ]);

            return null;
        }
    }

    /**
     * Mapea el JSON de SportsData.io al formato interno del modelo Event.
     *
     * Campos del JSON de SportsData.io (verificados en producción):
     *   GameId, Status, Clock, Day, DateTime,
     *   HomeTeamName, AwayTeamName, HomeTeamScore, AwayTeamScore,
     *   HomeTeamKey, AwayTeamKey
     *
     * @param  array<string, mixed>  $apiGame  Game crudo de la API
     * @return array<string, mixed>
     */
    public function mapFixtureToEvent(array $apiGame): array
    {
        $status = $this->mapStatus($apiGame['Status'] ?? 'Scheduled');

        return [
            'external_id'     => (string) ($apiGame['GameId'] ?? ''),
            'sport'           => 'soccer',
            'league'          => $this->competition,
            'home_team'       => $apiGame['HomeTeamName'] ?? 'Home',
            'away_team'       => $apiGame['AwayTeamName'] ?? 'Away',
            'home_score'      => (int) ($apiGame['HomeTeamScore'] ?? 0),
            'away_score'      => (int) ($apiGame['AwayTeamScore'] ?? 0),
            'status'          => $status,
            'elapsed_minutes' => $this->parseElapsedMinutes($apiGame['Clock'] ?? null),
            'starts_at'       => isset($apiGame['DateTime'])
                ? date('Y-m-d H:i:s', strtotime($apiGame['DateTime']))
                : null,
            'raw_data'        => $apiGame,
        ];
    }

    /**
     * Convierte el status de SportsData.io al formato interno.
     *
     * Statuses conocidos de SportsData.io Soccer:
     *   "Scheduled"  → partido no iniciado
     *   "InProgress" → partido en curso (LIVE)
     *   "HalfTime"   → descanso (sigue siendo "live" para nuestros cálculos)
     *   "Final"      → partido finalizado (tiempo normal)
     *   "F/OT"       → finalizado en tiempo extra
     *   "F/PKs"      → finalizado en penales
     *   "Suspended"  → suspendido
     *   "Postponed"  → pospuesto
     *   "Canceled"   → cancelado
     */
    private function mapStatus(string $apiStatus): string
    {
        return match ($apiStatus) {
            'Scheduled'           => 'scheduled',
            'InProgress', 'HalfTime' => 'live',
            'Final', 'F/OT', 'F/PKs' => 'finished',
            'Suspended', 'Postponed', 'Canceled' => 'postponed',
            default => 'scheduled',
        };
    }

    /**
     * Extrae los minutos jugados del campo Clock de SportsData.io.
     *
     * El campo Clock puede venir como:
     *   null → partido no iniciado o finalizado
     *   "74'" → minuto 74
     *   "90+2'" → 90 + descuento = 92
     *   "45'" → minuto 45
     */
    private function parseElapsedMinutes(?string $clock): ?int
    {
        if ($clock === null || $clock === '') {
            return null;
        }

        // Remover comilla final y espacios
        $clock = trim(str_replace("'", '', $clock));

        // Manejar "90+2" → 92
        if (str_contains($clock, '+')) {
            [$base, $extra] = explode('+', $clock);

            return (int) $base + (int) $extra;
        }

        return (int) $clock;
    }

    /**
     * Realiza la petición HTTP autenticada con SportsData.io.
     * Auth: header "Ocp-Apim-Subscription-Key"
     */
    private function makeRequest(string $endpoint): Response
    {
        return Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
        ])
            ->timeout(15)
            ->get($this->baseUrl.$endpoint);
    }
}
