<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $d = $dashboard->forUser($request->user());

        return view('analytics.index', ['carriers' => $dashboard->carrierStats($request->user()), 'd' => $d]);
    }
}
