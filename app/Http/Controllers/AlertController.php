<?php

namespace App\Http\Controllers;

use App\Services\AlertFeed;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request, AlertFeed $feed): View
    {
        $f = $request->validate(['level' => ['nullable', 'in:critical,warning,info'], 'kind' => ['nullable', 'in:exception,delay']]);

        $items = $feed->items($request->user())
            ->when($f['level'] ?? null, fn ($c, $v) => $c->where('level', $v))
            ->when($f['kind'] ?? null, fn ($c, $v) => $c->where('kind', $v))->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($items->forPage($page, 15)->values(), $items->count(), 15, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('alerts.index', ['alerts' => $paginator, 'filters' => $f]);
    }
}
