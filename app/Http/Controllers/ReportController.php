<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private AuditLogger $audit)
    {
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'in:CREATED,BOOKED,PICKED_UP,IN_TRANSIT,DELIVERED,CANCELLED'],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
    }

    public function index(Request $request): View
    {
        $f = $this->filters($request);
        $q = $this->reports->query($request->user(), $f);

        return view('reports.index', [
            'rows' => (clone $q)->paginate(15)->withQueryString(),
            'summary' => $this->reports->summary($q),
            'filters' => $f,
            'carriers' => Carrier::orderBy('name')->get(['id', 'name']),
            'print' => false,
        ]);
    }

    /** Print-friendly page; use the browser's "Save as PDF". */
    public function print(Request $request): View
    {
        $f = $this->filters($request);
        $q = $this->reports->query($request->user(), $f);

        return view('reports.print', ['rows' => (clone $q)->limit(500)->get(), 'summary' => $this->reports->summary($q), 'filters' => $f]);
    }

    public function csv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $f = $this->filters($request);
        $this->audit->log('report.exported', null, null, ['format' => 'csv'] + $f, $request->user());
        $q = $this->reports->query($request->user(), $f);

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Shipment', 'Customer', 'Carrier', 'Origin', 'Destination', 'Status', 'Priority', 'Expected arrival', 'ETA', 'Delivered', 'Delayed', 'Delay hours', 'Severity', 'Exceptions']);
            $q->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $s) {
                    fputcsv($out, array_map([ReportService::class, 'cell'], [
                        $s->shipment_number, $s->customer->name, $s->carrier->name, $s->origin->city, $s->destination->city, $s->status->value,
                        $s->priority?->value ?? 'NORMAL', $s->expected_arrival_at, $s->estimated_arrival_at ?? '', $s->delivered_at ?? '',
                        $s->is_delayed ? 'yes' : 'no', $s->delay_hours, $s->delay_severity?->value ?? '', $s->exceptions_count,
                    ]));
                }
            });
            fclose($out);
        }, 'rekacy-shipments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
