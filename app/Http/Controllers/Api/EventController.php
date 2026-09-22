<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * GET /api/events
     * Lista eventos con filtros opcionales por status y fecha.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();

        // Filtrar por estado: ?status=live|scheduled|finished
        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        // Filtrar partidos de hoy o de una fecha específica: ?date=2026-09-21
        if ($request->has('date')) {
            $query->whereDate('starts_at', $request->string('date'));
        }

        // Solo eventos en vivo y de hoy por defecto
        if (! $request->has('status') && ! $request->has('date')) {
            $query->whereIn('status', ['live', 'scheduled'])
                ->whereDate('starts_at', today());
        }

        $events = $query->orderBy('starts_at')->paginate(20);

        return response()->json($events);
    }

    /**
     * GET /api/events/{event}
     * Detalle de un evento con sus picks activos.
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json($event->load('picks'));
    }
}
