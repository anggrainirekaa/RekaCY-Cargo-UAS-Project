<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\DashboardService;
use App\Services\GeocodingService;
use App\Services\SearchService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** External-API backed helpers and the dashboard payload (used by both web and API routes). */
class InsightController extends Controller
{
    public function dashboard(Request $request, DashboardService $dashboard): JsonResponse
    {
        $d = $dashboard->forUser($request->user());

        return response()->json([
            'totals' => $d['totals'], 'rates' => $d['rates'], 'kpis' => $d['kpis'],
            'by_status' => $d['by_status'], 'by_carrier' => $d['by_carrier'], 'weekly_volume' => $d['weekly_volume'],
        ]);
    }

    public function search(Request $request, SearchService $search): JsonResponse
    {
        $q = (string) ($request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);

        return response()->json($search->search($request->user(), $q, 10));
    }

    public function weather(Request $request, Shipment $shipment, WeatherService $weather): JsonResponse
    {
        Gate::authorize('view', $shipment);
        $shipment->load('latestLocatedEvent');
        $e = $shipment->latestLocatedEvent;

        if (! $e) {
            return response()->json(['ok' => false, 'data' => null, 'error' => 'No tracked position yet.']);
        }

        return response()->json($weather->current($e->latitude, $e->longitude) + ['place' => $e->location_name]);
    }

    public function geocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        Gate::authorize('operate');
        $q = $request->validate(['q' => ['required', 'string', 'min:3', 'max:150']])['q'];

        return response()->json($geocoding->search($q));
    }
}
