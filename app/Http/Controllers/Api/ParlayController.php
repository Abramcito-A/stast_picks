<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parlay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParlayController extends Controller
{
    /**
     * GET /api/parlays
     * Lista todos los parlays del usuario autenticado con sus picks y eventos.
     */
    public function index(Request $request): JsonResponse
    {
        $parlays = $request->user()
            ->parlays()
            ->with(['picks.event'])
            ->latest()
            ->paginate(10);

        return response()->json($parlays);
    }

    /**
     * POST /api/parlays
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'stake'            => ['nullable', 'numeric', 'min:0'],
            'potential_payout' => ['nullable', 'numeric', 'min:0'],
        ]);

        $parlay = $request->user()->parlays()->create($validated);

        return response()->json($parlay->load('picks'), 201);
    }

    /**
     * GET /api/parlays/{parlay}
     */
    public function show(Request $request, Parlay $parlay): JsonResponse
    {
        $this->authorizeParlay($request, $parlay);

        return response()->json($parlay->load(['picks.event']));
    }

    /**
     * PUT /api/parlays/{parlay}
     */
    public function update(Request $request, Parlay $parlay): JsonResponse
    {
        $this->authorizeParlay($request, $parlay);

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'stake'            => ['sometimes', 'numeric', 'min:0'],
            'potential_payout' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $parlay->update($validated);

        return response()->json($parlay->load('picks'));
    }

    /**
     * DELETE /api/parlays/{parlay}
     */
    public function destroy(Request $request, Parlay $parlay): JsonResponse
    {
        $this->authorizeParlay($request, $parlay);

        $parlay->delete();

        return response()->json(['message' => 'Parlay eliminado correctamente.']);
    }

    private function authorizeParlay(Request $request, Parlay $parlay): void
    {
        abort_if($parlay->user_id !== $request->user()->id, 403, 'No autorizado.');
    }
}
