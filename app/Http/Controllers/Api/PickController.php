<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parlay;
use App\Models\Pick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PickController extends Controller
{
    /**
     * GET /api/parlays/{parlay}/picks
     */
    public function index(Request $request, Parlay $parlay): JsonResponse
    {
        $this->authorizeParlay($request, $parlay);

        $picks = $parlay->picks()->with('event')->get();

        return response()->json($picks);
    }

    /**
     * POST /api/parlays/{parlay}/picks
     * Añade un pick a un parlay existente.
     */
    public function store(Request $request, Parlay $parlay): JsonResponse
    {
        $this->authorizeParlay($request, $parlay);

        $validated = $request->validate([
            'event_id'     => ['required', 'exists:events,id'],
            'pick_type'    => ['required', 'in:total_goals,home_score,away_score,result'],
            'condition'    => ['required', 'in:over,under,exact,home,away,draw'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'odd'          => ['nullable', 'numeric', 'min:1'],
        ]);

        $pick = $parlay->picks()->create($validated);

        return response()->json($pick->load('event'), 201);
    }

    /**
     * GET /api/picks/{pick}
     */
    public function show(Request $request, Pick $pick): JsonResponse
    {
        $this->authorizePick($request, $pick);

        return response()->json($pick->load('event'));
    }

    /**
     * DELETE /api/picks/{pick}
     */
    public function destroy(Request $request, Pick $pick): JsonResponse
    {
        $this->authorizePick($request, $pick);

        $pick->delete();

        return response()->json(['message' => 'Pick eliminado correctamente.']);
    }

    private function authorizeParlay(Request $request, Parlay $parlay): void
    {
        abort_if($parlay->user_id !== $request->user()->id, 403, 'No autorizado.');
    }

    private function authorizePick(Request $request, Pick $pick): void
    {
        abort_if($pick->parlay->user_id !== $request->user()->id, 403, 'No autorizado.');
    }
}
