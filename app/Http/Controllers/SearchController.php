<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $search): View
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return view('search.index', ['q' => $q, 'results' => $search->search($request->user(), $q, 10)]);
    }

    public function json(Request $request, SearchService $search): JsonResponse
    {
        $q = (string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');

        return response()->json($search->search($request->user(), $q, 5));
    }
}
