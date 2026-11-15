<?php

namespace App\Http\Controllers;

use App\Services\MapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingMapController extends Controller
{
    /** Global tracking / route monitoring: full-height map + searchable route list. */
    public function index(Request $request, MapService $maps): View
    {
        return view('tracking.index', ['map' => $maps->build($request->user(), ['limit' => 150]), 'q' => (string) $request->query('q', '')]);
    }
}
