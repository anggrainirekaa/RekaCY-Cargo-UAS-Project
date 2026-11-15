<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view-audit-log');
        $f = $request->validate(['action' => ['nullable', 'string', 'max:60'], 'entity' => ['nullable', 'string', 'max:80']]);

        return view('audit.index', [
            'logs' => AuditLog::query()->with('user:id,name')
                ->when($f['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', addcslashes($v, '%_\\').'%'))
                ->when($f['entity'] ?? null, fn ($q, $v) => $q->where('entity_type', $v))
                ->latest('id')->paginate(20)->withQueryString(),
            'filters' => $f,
        ]);
    }
}
