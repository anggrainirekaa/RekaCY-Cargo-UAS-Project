<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\MapService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, MapService $maps): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        if ($user->isCourier()) {
            return redirect()->route('courier.index');
        }

        return $user->isStaff()
            ? view('dashboard', ['d' => $dashboard->commandCenter($user)])
            : view('dashboard-customer', ['d' => $dashboard->forUser($user), 'map' => $maps->build($user, ['limit' => 40])]);
    }
}
